/**
 * スクロール矢印（ページ上下移動 + 表示制御）
 *
 * - .page-scroll がスクロール対象のため、window ではなく scroller を監視する
 * - スクロール量に応じて .show クラスを付け外しする
 * - クリックで最上部・最下部へスムーズスクロール
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    // ▼ 上へ・下へボタンの取得
    const btnTop = document.getElementById('scroll-top');
    const btnBottom = document.getElementById('scroll-bottom');

    // ボタンが存在しない場合は処理しない
    if (!btnTop && !btnBottom) return;

    // ▼ スクロール対象のコンテナ（window ではない）
    const scroller = document.querySelector('.page-scroll');
    if (!scroller) return;

    /**
     * 指定位置へスムーズスクロール
     * @param {number} y - スクロール先の位置
     */
    function scrollToY(y) {
      scroller.scrollTo({ top: y, behavior: 'smooth' });
    }

    // ▼ ボタンクリックでスクロール
    btnTop.addEventListener('click', function () {
      scrollToY(0);
    });

    btnBottom.addEventListener('click', function () {
      scrollToY(scroller.scrollHeight);
    });

    /**
     * スクロール位置に応じて矢印の表示・非表示を切り替える
     */
    function toggleButtons() {
      const scrollY = scroller.scrollTop; // 現在のスクロール量
      const maxScroll = scroller.scrollHeight - scroller.clientHeight;

      // ▼ 上へボタン（200px 以上スクロールしたら表示）
      if (scrollY > 200) {
        btnTop.classList.add('show');
      } else {
        btnTop.classList.remove('show');
      }

      // ▼ 下へボタン（最下部の手前 200px まで表示）
      if (scrollY < maxScroll - 200) {
        btnBottom.classList.add('show');
      } else {
        btnBottom.classList.remove('show');
      }
    }

    // ▼ スクロールイベントを scroller に設定
    scroller.addEventListener('scroll', toggleButtons);

    // 初期表示の判定
    toggleButtons();
  });
})();
