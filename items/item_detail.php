<?php
declare(strict_types=1);

/**
 * 作品詳細ページ（最終形）
 *
 * 方針：
 * - DB例外・不正操作は「入口で一括 catch」する
 * - CSRF は POST 処理の入口で検証（csrf.php の validateCSRFToken() に準拠）
 * - CSRF hidden は embedCSRFToken() を使用（csrf_token 変数は持たない）
 * - 表示と処理の責務を分離（ページ専用関数で集約）
 * - 不正/例外時は redirect_guard 経由で安全な場所へ退避
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';
require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../app/validators/review_validator.php';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/rating.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数
require_once __DIR__ . '/../includes/header_nav.php';

// セッションは必ず安全に開始
startSecureSession();

/* =========================
   ページ専用関数（このページ内だけで使用）
   ========================= */

/**
 * 作品情報取得（存在しない場合は例外）
 */
function fetchItem(PDO $pdo, int $item_id): array
{
  $stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = ?');
  $stmt->execute([$item_id]);
  $item = $stmt->fetch();
  if (!$item) {
      throw new RuntimeException('商品が見つかりません。');
  }
  return $item;
}

/**
 * レビュー一覧取得（退会ユーザー対応のため LEFT JOIN）
 */
function fetchReviews(PDO $pdo, int $item_id): array
{
  $stmt = $pdo->prepare("
      SELECT
        r.*,
        u.name  AS user_name,
        u.image AS user_image
      FROM reviews r
      LEFT JOIN users u
        ON u.user_id = r.user_id
      WHERE r.item_id = ?
      ORDER BY r.created_at DESC
  ");
  $stmt->execute([$item_id]);
  return $stmt->fetchAll();
}

/**
 * 返信をまとめて取得（N+1回避）
 * 返り値：review_id => replies[] の連想配列
 */
function fetchRepliesByReview(PDO $pdo, array $review_ids): array
{
  if (empty($review_ids)) {
    return [];
  }

  $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
  $stmt = $pdo->prepare("
      SELECT
        rr.*,
        u.name  AS reply_user_name,
        u.image AS reply_user_image
      FROM review_replies rr
      LEFT JOIN users u
        ON u.user_id = rr.user_id
      WHERE rr.review_id IN ($placeholders)
      ORDER BY rr.created_at ASC
  ");
  $stmt->execute($review_ids);

  $result = [];
  foreach ($stmt->fetchAll() as $row) {
    $result[(int)$row['review_id']][] = $row;
  }
  return $result;
}

/**
 * 評価(1〜5)ごとの件数を取得し、必ず 1〜5 が揃う配列にして返す
 * 返り値： [1=>cnt, 2=>cnt, 3=>cnt, 4=>cnt, 5=>cnt]
 */
function fetchRatingCounts(PDO $pdo, int $item_id): array
{
  $stmt = $pdo->prepare("
    SELECT rating, COUNT(*) AS cnt
    FROM reviews
    WHERE item_id = ?
    GROUP BY rating
    ORDER BY rating
  ");
  $stmt->execute([$item_id]);
  $rows = $stmt->fetchAll();

  // 1〜5が必ず揃うように0埋め
  $rating_counts = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];

  foreach ($rows as $row) {
    $r = (int)$row['rating'];
    if ($r >= 1 && $r <= 5) {
        $rating_counts[$r] = (int)$row['cnt'];
    }
  }

  return $rating_counts;
}

/**
 * 平均評価を取得する（レビュー0件なら 0 を返す）
 */
function fetchMeanRating(PDO $pdo, int $item_id): float
{
  $stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE item_id = ?");
  $stmt->execute([$item_id]);
  $mean = $stmt->fetchColumn();

  return ($mean === null) ? 0.0 : (float)$mean;
}

try {
  /* =========================
      基本情報・ガード
      ========================= */

  // item_id 取得と妥当性確認（GET必須）
  $item_id = (int)($_GET['item_id'] ?? 0);
  if ($item_id <= 0) {
    redirectWithError('不正なアクセスです。');
  }

  // ログイン状態（ゲスト閲覧OK、投稿はログイン必須）
  $is_logged_in = !empty($_SESSION['user_id']);
  $user_id      = $is_logged_in ? (int)$_SESSION['user_id'] : null;
  $can_post     = $is_logged_in;

  // 画面表示用メッセージ格納
  $errors = [];
  $review_errors = [];
  $reply_errors  = [];
  $reply_target_review_id = 0;

  // 入力保持（レビューのみ）
  $old_comment = '';
  $old_rating  = 0;

  // redirect_guard 経由で積まれたセッションエラーがある場合、画面側で表示するために取り込む
  if (!empty($_SESSION['error']) && is_array($_SESSION['error'])) {
    $errors = array_merge($errors, $_SESSION['error']);
    unset($_SESSION['error']);
  }

  // DB接続（例外は catch に集約）
  $pdo = getPdo();

  /* =========================
      POST処理（レビュー投稿／返信投稿）
      ========================= */

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // CSRF検証（最優先）
    // csrf.php の validateCSRFToken() は POST から csrf_token を読み、セッションと比較する（hash_equals）
    if (!validateCSRFToken()) {
        redirectWithError('不正な操作です。', './item_detail.php?item_id=' . $item_id);
    }

    // UIでフォームを非表示にしていても直POSTされる可能性があるため、サーバ側で必ず拒否
    if (!$can_post) {
        $errors[] = 'ログインが必要です。';
    } else {

      // POSTされた処理種別（想定外は default で弾く）
      $action = (string)($_POST['action'] ?? '');

      // レビュー投稿時のみ入力保持（エラー時に差し戻すため）
      if ($action === 'add_review') {
        $old_comment = (string)($_POST['comment'] ?? '');
        $old_rating  = (int)($_POST['rating'] ?? 0);
      }

      // action ホワイトリスト（switch の default で不正を弾く）
      switch ($action) {

        /* -----------------------------
            レビュー投稿
            ----------------------------- */
        case 'add_review':
          // 入力バリデーション（共通関数）
          $review_errors = validateAddReview($_POST);

          // 仕様：1ユーザーにつき1作品1レビュー（DBで確認）
          if (!$review_errors) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM reviews WHERE user_id = ? AND item_id = ? LIMIT 1'
            );
            $stmt->execute([$user_id, $item_id]);
            if ($stmt->fetchColumn()) {
              $review_errors[] = 'この作品へのレビューは1回までです。';
            }
          }

          // エラーが無ければ登録してPRG
          if (!$review_errors) {
            $stmt = $pdo->prepare(
              'INSERT INTO reviews (user_id, comment, item_id, rating) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $user_id,
                trim((string)($_POST['comment'] ?? '')),
                $item_id,
                (int)($_POST['rating'] ?? 0),
            ]);

            // PRG（二重投稿防止）
            header('Location: ' . $_SERVER['PHP_SELF'] . '?item_id=' . $item_id);
            exit;
          }
          break;

          /* -----------------------------
              返信投稿
              ----------------------------- */
        case 'add_reply':
          // エラー表示位置を特定するため、返信対象 review_id を保持
          $reply_target_review_id = (int)($_POST['review_id'] ?? 0);

          // 入力バリデーション（共通関数）
          $review_errors = validateAddReview($_POST);

          // 返信先レビューがこの作品に属しているか確認（DB整合性）
          if (!$reply_errors) {
            $stmt = $pdo->prepare(
                'SELECT review_id FROM reviews WHERE review_id = ? AND item_id = ?'
            );
            $stmt->execute([$reply_target_review_id, $item_id]);
            if (!$stmt->fetch()) {
              $reply_errors[] = '返信先レビューが存在しません。';
            }
          }

          // エラーが無ければ登録してPRG
          if (!$reply_errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO review_replies (review_id, user_id, comment) VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $reply_target_review_id,
                $user_id,
                trim((string)($_POST['reply_text'] ?? '')),
            ]);

            // PRG（二重投稿防止）
            header('Location: ' . $_SERVER['PHP_SELF'] . '?item_id=' . $item_id);
            exit;
          }
          break;

        default:
          redirectWithError('不正な操作です。', './item_detail.php?item_id=' . $item_id);
      }
    }
  }

  /* =========================
      表示用データ取得
      ========================= */

  $item    = fetchItem($pdo, $item_id);
  $reviews = fetchReviews($pdo, $item_id);

  $review_ids = array_column($reviews, 'review_id');
  $replies_by_review = fetchRepliesByReview($pdo, $review_ids);

  // ★表示・グラフ用（HTMLで使用するので必ず生成）
  $rating_counts = fetchRatingCounts($pdo, $item_id);
  $rating_counts_js = json_encode(array_values($rating_counts), JSON_UNESCAPED_UNICODE);
  $mean = fetchMeanRating($pdo, $item_id);

} catch (\RuntimeException $e) {
    // 1. 商品が見つからないなどの「想定内」の業務エラー
    // 開発者用ログには詳細を記録
    error_log('item_detail.php business error: ' . $e->getMessage());
    
    // ユーザーには例外のメッセージ（「商品が見つかりません。」）をそのまま表示
    redirectWithError($e->getMessage());

} catch (\Throwable $e) {
    // 2. DB接続エラーやプログラムのバグなど「想定外」の致命的エラー
    // 開発者用ログにはエラー内容とスタックトレースを記録
    error_log('item_detail.php fatal error: ' . $e->getMessage());
    error_log($e->getTraceAsString());

    // ユーザーには詳細（DB構造など）を見せず、安全な汎用メッセージを表示
    redirectWithError('処理中にエラーが発生しました。');
}
?>

<!doctype html>
<html lang="ja">
<head>
  <!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

  <link rel="stylesheet" href="../css/item_detail.css">
  <link rel="stylesheet" href="../css/scroll_arrow.css">
  <title>仮想映画レビュー　<?= sanitize($item['title']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/c3/0.7.20/c3.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/5.16.0/d3.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.7.20/c3.min.js"></script>
</head>

<body>

<!-- =========================
     ヘッダー
     ========================= -->
<header>
  <h1>仮想映画レビュート　作品詳細</h1>
  <nav>
    <?php outputHeaderNav(); ?>
  </nav>
</header>
<div class="page-scroll">
<!-- =========================
     メインコンテンツ
     ========================= -->
<main>

  <!-- 作品タイトル -->
  <h1><?= sanitize($item['title']) ?></h1>

  <!-- 作品概要 -->
  <div class="item-detail-wrap">
    <div class="item-detail-image-area">
      <?php if (!empty($item['image'])): ?>
        <img class="item-detail-image"
             src="../images/thumbnail/<?= sanitize($item['image']) ?>"
             alt="<?= sanitize($item['title']) ?>">
      <?php else: ?>
        <img class="item-detail-image no-image"
             src="../images/no_image/no_image.png"
             alt="画像なし">
      <?php endif; ?>
    </div>

    <div class="item-detail-description">
      <p><?= nl2br(sanitize($item['description'] ?? '')) ?></p>
    </div>
  </div>

  <!-- 画面共通エラー -->
  <?php if (!empty($errors)): ?>
    <div class="error">
      <?php foreach ($errors as $e): ?>
        <div><?= sanitize((string)$e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- レビュー投稿（ログイン時のみ表示） -->
  <?php if ($can_post): ?>
    <div class="box">
      <h2>レビューを書く</h2>

      <?php if (!empty($review_errors)): ?>
        <div class="error">
          <?php foreach ($review_errors as $e): ?>
            <div><?= sanitize((string)$e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="note">※ この作品へのレビューは、お一人様1回まで投稿できます。</p>

      <form method="post">
        <input type="hidden" name="action" value="add_review">

        <!-- CSRF hidden（csrf.php の embedCSRFToken() を使用） -->
        <?php embedCSRFToken(); ?>

        <div>
          <label>評価（1〜5）</label><br>
          <select name="rating" required>
            <option value="">選択してください</option>
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <option value="<?= $i ?>" <?= ($old_rating === $i ? 'selected' : '') ?>>
                <?= $i ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <div style="margin-top:8px;">
          <label>本文</label><br>
          <textarea name="comment" required><?= sanitize($old_comment) ?></textarea>
        </div>

        <button type="submit">投稿</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- 評価サマリー -->
  <h2>作品の評価</h2>

  <div class="item-rating">
    <span class="rating-label">平均評価</span>
    <?= renderStarRating($mean) ?>
  </div>

  <p>レビュー件数：<?= count($reviews) ?></p>
  <div id="ratingChart"></div>

  <!-- レビュー一覧 -->
  <h2>レビュー一覧</h2>

  <?php if (empty($reviews)): ?>
    <p>まだレビューがありません。</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <?php
        // 退会ユーザー等のフォールバック
        $reviewUserName  = !empty($r['user_name']) ? $r['user_name'] : '退会済みユーザー';
        $reviewUserImage = !empty($r['user_image']) ? $r['user_image'] : null;
      ?>

      <div class="box">
        <?php if ($reviewUserImage): ?>
          <img src="<?php echo getBaseUrl(); ?>/images/icon/<?= sanitize($reviewUserImage) ?>"
               alt="アイコン"
               style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
          <img src="<?php echo getBaseUrl(); ?>/images/no_image/no_image.png"
               alt="アイコン"
               style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php endif; ?>

        <div>
          <strong><?= sanitize($reviewUserName) ?></strong>
          / 評価: <?= (int)$r['rating'] ?>
        </div>

        <div><?= nl2br(sanitize($r['comment'])) ?></div>
        <div class="meta"><?= sanitize((string)$r['created_at']) ?></div>

        <!-- 返信一覧 -->
        <?php $replies = $replies_by_review[(int)$r['review_id']] ?? []; ?>
        <?php if (!empty($replies)): ?>
          <?php foreach ($replies as $rr): ?>
            <?php
              $replyUserName  = !empty($rr['reply_user_name']) ? $rr['reply_user_name'] : '退会済みユーザー';
              $replyUserImage = !empty($rr['reply_user_image']) ? $rr['reply_user_image'] : null;
            ?>
            <div class="reply">
              <?php if ($replyUserImage): ?>
                <img src="../images/icon/<?= sanitize($replyUserImage) ?>"
                     alt="アイコン"
                     style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
              <?php else: ?>
                <img src="../images/no_image/no_image.png"
                     alt="アイコン"
                     style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
              <?php endif; ?>

              <div>
                <div><strong><?= sanitize($replyUserName) ?></strong>（返信）</div>
                <div><?= nl2br(sanitize($rr['comment'])) ?></div>
                <div class="meta"><?= sanitize((string)$rr['created_at']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- 返信フォーム（ログイン時のみ表示） -->
        <?php if ($can_post): ?>
          <div class="reply">

            <?php if (!empty($reply_errors) && $reply_target_review_id === (int)$r['review_id']): ?>
              <div class="error">
                <?php foreach ($reply_errors as $e): ?>
                  <div><?= sanitize((string)$e) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="action" value="add_reply">
              <input type="hidden" name="review_id" value="<?= (int)$r['review_id'] ?>">

              <!-- CSRF hidden（返信フォームにも必須） -->
              <?php embedCSRFToken(); ?>

              <textarea name="reply_text" placeholder="このレビューへの返信" required></textarea>
              <button type="submit">返信する</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="top-link">
    <a href="item_list.php">作品一覧へ</a>
  </div>

  <?php if ($is_logged_in): ?>
    <div class="top-link">
      <a href="../mypage/mypage.php">マイページTOPへ</a>
    </div>
  <?php endif; ?>

</main>

<!-- フッター -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ページ最下部へ -->
<div id="scroll-bottom" class="scroll-arrow scroll-arrow--bottom" title="ページ最下部へ">↓</div>

<!-- ページトップへ -->
<div id="scroll-top" class="scroll-arrow scroll-arrow--top" title="ページトップへ">↑</div>

<!-- スクロール矢印（ページ上下移動） -->
<script src="../js/scroll_arrow.js?v=1"></script>
</div>

<!-- 評価分布グラフ描画 -->
<script>
  // PHP→JS：件数を「数値」に統一
  const counts = (<?= $rating_counts_js ?>).map(n => Number(n));
  const maxCnt = Math.max(...counts, 0);

  // 目盛り間隔(step)を「1/2/5 × 10^n」に丸めて、見やすくする
  function niceStep(max, targetTicks = 6) {
    if (max <= 0) return 1;
    const rough = max / targetTicks;
    const pow10 = Math.pow(10, Math.floor(Math.log10(rough)));
    const base  = rough / pow10;
    const niceBase = (base <= 1) ? 1 : (base <= 2) ? 2 : (base <= 5) ? 5 : 10;
    return niceBase * pow10;
  }

  const step = niceStep(maxCnt, 6);

  const ticks = [];
  const topValue = Math.ceil(maxCnt / step) * step;
  for (let v = 0; v <= topValue; v += step) {
    ticks.push(v);
  }

  c3.generate({
    bindto: '#ratingChart',
    data: {
      columns: [
        ['件数', ...counts]
      ],
      type: 'bar'
    },
    axis: {
      x: {
        type: 'category',
        categories: ['1', '2', '3', '4', '5'],
        label: '評価'
      },
      y: {
        label: '件数',
        min: 0,
        tick: {
          values: ticks,
          format: d3.format(',d')
        }
      }
    }
  });
</script>

</body>
</html>
