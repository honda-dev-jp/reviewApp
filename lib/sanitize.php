<?php
/**
 * サニタイズ用関数
 *
 * @param string|array $before サニタイズする文字列または配列
 * @return string|array サニタイズ後の文字列または配列
 */
function sanitize($before) {
    $after = array();
    if (!is_array($before)) {
        return htmlspecialchars($before, ENT_QUOTES, 'UTF-8');
    }
    foreach($before as $key => $value){
        $after[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $after;
}
