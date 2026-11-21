<?php
define('LOGIN_PAGE', true);                       // auth_check.php 의 로그인 체크를 건너뛰기 위한 표시
require_once __DIR__ . '/auth_check.php';         // 세션만 시작

/**
 * userInfo.ini 읽기 (같은 폴더) - 아래 두 가지 형식을 모두 지원
 *
 *  [형식 A] 한 줄에 쉼표로 구분 (같은 순서끼리 짝)
 *      userId:wingle,tjclub
 *      userPassword:!비번1,!비번2
 *
 *  [형식 B] 태그 아래에 한 줄에 하나씩 (같은 순서끼리 짝)
 *      <userId>
 *      wingle
 *      tjclub
 *      <userPassword>
 *      !비번1
 *      !비번2
 *
 * 주석 : 줄의 첫 글자가 # 이면 그 줄은 무시합니다. (예: # 홍길동 계정)
 * 비밀번호 값은 평문이어도, 해시($2y$... 등)여도 됩니다. (로그인 시 자동 판별)
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
    $section = null;                                       // 형식 B 에서 현재 읽는 태그

    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if ($line[0] === '#') continue;                    // 줄의 첫 글자가 # 이면 주석 (태그 안/밖 모두)

        // 형식 B : <userId> / <userPassword> (닫는 태그 </userId> 도 허용)
        if (preg_match('/^<\s*(\/?)\s*(userid|userpassword)\s*>$/i', $line, $m)) {
            $section = ($m[1] === '/') ? null : strtolower($m[2]);
            continue;
        }
        if ($section !== null) {                           // 태그 안의 나머지 줄은 한 줄에 하나씩 값으로 사용
            if ($section === 'userid') $ids[] = $line; else $pws[] = $line;
            continue;
        }

        // 형식 A : 키:값,값
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

/** 저장된 값이 password_hash() 결과(bcrypt / argon2)인지 판별 */
function isPasswordHash(string $v): bool
{
    return (bool)preg_match('/^\$(2[abxy]\$\d{2}\$.{53}|argon2(id|i|d)\$.+)$/', $v);
}

/** 비밀번호 검사 : 해시면 password_verify, 아니면 평문 그대로 비교 */
function checkPassword(string $input, string $stored): bool
{
    return isPasswordHash($stored)
        ? password_verify($input, $stored)
        : hash_equals($stored, $input);
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
        foreach (loadUserInfo(__DIR__ . '/userInfo.ini') as $u) {
            // ID/비밀번호 모두 대소문자 구분 (비밀번호는 평문/해시 자동 판별)
            $idOk = hash_equals($u['id'], $userId);
            $pwOk = checkPassword($password, $u['pw']);
            if ($idOk && $pwOk) {
                $matchedId = $u['id'];
                break;
            }
        }
    }

    if ($matchedId !== null) {
        session_regenerate_id(true);              // 세션 고정 공격 방지
        $_SESSION['user_id'] = $matchedId;
        $_SESSION['user_nm'] = $matchedId;
        $_SESSION['last_activity'] = time();      // 마지막 서버 통신 시각 (30분 무활동 시 auth_check.php 가 자동 로그아웃)
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
