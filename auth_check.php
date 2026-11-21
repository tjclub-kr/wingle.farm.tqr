<?php
/**
 * auth_check.php : 로그인 체크 공용 루틴
 *
 * 사용법 : 각 PHP 페이지의 "맨 위"(어떤 출력보다 먼저)에 아래 한 줄을 추가
 *     require_once __DIR__ . '/auth_check.php';
 *
 *  - 로그인 상태      : 아무 일 없이 통과
 *  - 미로그인 + 일반 접근 : login.php 로 이동
 *  - 미로그인 + AJAX(fetch) : HTTP 401 응답 (menu.php 가 받아서 login.php 로 이동시킴)
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $lifetime = 60 * 60 * 24 * 7;                 // 로그인 유지 기간 : 7일 (필요 시 변경)
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 마지막 서버 통신(요청) 후 이 시간(초)이 지나면 자동 로그아웃 : 30분
// (menu.php 의 IDLE_LOGOUT_MS 와 같은 값으로 맞추세요)
if (!defined('IDLE_TIMEOUT_SEC')) {
    define('IDLE_TIMEOUT_SEC', 30 * 60);
}

// 무활동 시간 초과 시 로그인 정보를 지움 (login.php 에서도 적용되도록 LOGIN_PAGE 검사보다 위에 둠)
if (!empty($_SESSION['user_id'])
    && (time() - (int)($_SESSION['last_activity'] ?? 0)) > IDLE_TIMEOUT_SEC) {
    $_SESSION = [];
}

// login.php 는 세션만 시작하고 체크는 건너뜀 (무한 이동 방지)
if (defined('LOGIN_PAGE')) {
    return;
}

if (empty($_SESSION['user_id'])) {
    // fetch() 로 불러오는 요청인지 판별
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
           || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'empty');

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'LOGIN_REQUIRED';
        exit;
    }

    header('Location: login.php');
    exit;
}

// 로그인된 상태로 이 페이지를 호출했으므로 마지막 서버 통신 시각을 갱신
$_SESSION['last_activity'] = time();
