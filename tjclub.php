<?php
/**
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$path = __DIR__ . '/userinfo.ini';
if (!is_readable($path) || !is_writable($path)) {
    fwrite(STDERR, "userinfo.ini 를 읽거나 쓸 수 없습니다: $path\n");
    exit(1);
}

$original = file_get_contents($path);
$bom      = (strncmp($original, "\xEF\xBB\xBF", 3) === 0) ? "\xEF\xBB\xBF" : '';
$lines    = preg_split('/(\R)/', substr($original, strlen($bom)), -1, PREG_SPLIT_DELIM_CAPTURE);

$converted = 0;
$kept      = 0;

foreach ($lines as $i => $line) {
    $pos = strpos($line, ':');
    if ($pos === false) continue;
    if (strtolower(trim(substr($line, 0, $pos))) !== 'userpassword') continue;

    $head   = substr($line, 0, $pos + 1);                 // "userPassword:" (원래 표기 유지)
    $values = array_map('trim', explode(',', substr($line, $pos + 1)));

    foreach ($values as $k => $v) {
        if ($v === '') continue;
        if (preg_match('/^\$(2[abxy]|argon2(id|i|d))\$/', $v)) {   // 이미 해시
            $kept++;
        } else {
            $values[$k] = password_hash($v, PASSWORD_DEFAULT);
            $converted++;
        }
    }
    $lines[$i] = $head . implode(',', $values);
}

if ($converted === 0) {
    echo "변환할 평문 비밀번호가 없습니다. (이미 해시 {$kept}개)\n";
    exit(0);
}

copy($path, $path . '.bak');
file_put_contents($path, $bom . implode('', $lines), LOCK_EX);

echo "완료: {$converted}개 변환, {$kept}개는 이미 해시.\n";
echo "원본(평문)은 userinfo.ini.bak 에 있습니다. 로그인 확인 후 삭제하세요.\n";
