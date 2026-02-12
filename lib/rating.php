<?php
/**
 * 平均点評価を星表示(割合)でレンダリングする
 * 
 * @param float|null $avg 平均評価(nullの場合は未評価)
 * @param int $max 最大評価(通常5)
 * @return string HTML文字列
 */
function renderStarRating(?float $avg, int $max = 5): string
{
  if($avg === null){
    return '<span class="no-rating">評価なし</span>';
  }

  $avg = round($avg, 1);
  $percent = ($avg / $max) * 100;

  return '
    <div class="star-rating">
      <div class="star-bg">' . str_repeat('★', $max) . '</div>
      <div class="star-fg" style="width:' . $percent . '%">' . str_repeat('★', $max) . '</div>
    </div>
    <span class="rating-value">' . $avg . '</span>
  ';
}