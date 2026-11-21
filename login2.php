<?php
define('LOGIN_PAGE', true);                       // auth_check.php 의 로그인 체크를 건너뛰기 위한 표시
require_once __DIR__ . '/auth_check.php';         // 세션만 시작

/**
 * userinfo.ini 읽기 (같은 폴더)
 * 형식 (표준 INI 아님, "키:값" 한 줄씩, 값은 쉼표로 구분하며 같은 순서끼리 짝):
 *     userId:wingle,tjclub
 *     userPassword:$2y$10$해시값1,$2y$10$해시값2     (password_hash 결과, 평문 아님)
 * 반환 : [ ['id'=>..., 'pw'=>...], ... ]
 */
function loadUserInfo(string $path): array
{
    if (!is_readable($path)) return [];
    $text = file_get_contents($path);
    if ($text === false) return [];
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);   // UTF-8 BOM 제거

    $ids = [];
    $pws = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') continue;
        $pos = strpos($line, ':');
        if ($pos === false) continue;
        $key = strtolower(trim(substr($line, 0, $pos)));
        $val = substr($line, $pos + 1);
        if ($key === 'userid')       $ids = array_map('trim', explode(',', $val));
        if ($key === 'userpassword') $pws = array_map('trim', explode(',', $val));
    }

    $users = [];
    foreach ($ids as $i => $id) {
        if ($id !== '' && isset($pws[$i]) && $pws[$i] !== '') {
            $users[] = ['id' => $id, 'pw' => $pws[$i]];
        }
    }
    return $users;
}

// 이미 로그인된 상태면 메인으로
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$loginFailed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = trim($_POST['user_id'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $matchedId = null;
    if ($userId !== '' && $password !== '') {
        foreach (loadUserInfo(__DIR__ . '/userinfo.ini') as $u) {
            // ID 는 대소문자 구분, 비밀번호는 해시값과 password_verify 로 검증
            if (hash_equals($u['id'], $userId) && password_verify($password, $u['pw'])) {
                $matchedId = $u['id'];
                break;
            }
        }
    }

    if ($matchedId !== null) {
        session_regenerate_id(true);              // 세션 고정 공격 방지
        $_SESSION['user_id'] = $matchedId;
        $_SESSION['user_nm'] = $matchedId;
        header('Location: index.php');
        exit;
    }

    // 실패 : 무차별 대입 완화용 지연 후 오류 화면 표시
    sleep(1);
    $loginFailed = true;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }
        .box {
            width: 100%;
            max-width: 340px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 24px 20px;
        }
        h1 { font-size: 20px; color: #2c3e50; text-align: center; margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: bold; color: #555; margin-bottom: 5px; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 11px; border: 1px solid #ddd; border-radius: 6px;
            font-size: 16px; font-family: inherit; margin-bottom: 14px;
        }
        .btn {
            display: block; width: 100%; padding: 12px; border: none; border-radius: 6px;
            background: #2c3e50; color: #fff; font-size: 15px; font-weight: bold;
            text-align: center; text-decoration: none; cursor: pointer;
        }
        .err-msg { color: #c0392b; font-size: 17px; font-weight: bold; text-align: center; margin: 8px 0 22px; }
    </style>
</head>
<body>
<div class="box">
<?php if ($loginFailed): ?>
    <!-- 로그인 실패 화면 -->
    <h1>로그인</h1>
    <p class="err-msg">입력한 정보 오류</p>
    <a class="btn" href="login.php">로그인 화면으로 이동</a>
<?php else: ?>
    <!-- 로그인 입력 화면 -->
    <h1>로그인</h1>
    <form method="post" action="login.php" autocomplete="on">
        <label for="user_id">User Id</label>
        <input type="text" id="user_id" name="user_id" autocomplete="username" autocapitalize="off" required autofocus>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="btn">로그인</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
