-- 로그인 사용자 테이블 (MySQL / MariaDB 기준)
CREATE TABLE IF NOT EXISTS app_user (
    user_id        VARCHAR(50)  NOT NULL PRIMARY KEY,
    user_nm        VARCHAR(50)  NOT NULL DEFAULT '',
    password_hash  VARCHAR(255) NOT NULL,           -- password_hash() 결과 저장 (평문 저장 금지)
    use_yn         CHAR(1)      NOT NULL DEFAULT 'Y'
) DEFAULT CHARSET = utf8mb4;

-- 1) 서버 터미널에서 비밀번호 해시 생성 (내 비밀번호로 바꿔서 실행)
--      php -r "echo password_hash('내비밀번호', PASSWORD_DEFAULT), PHP_EOL;"
--
-- 2) 나온 해시값($2y$10$... 형태)을 아래에 넣어 등록
INSERT INTO app_user (user_id, user_nm, password_hash)
VALUES ('admin', '관리자', '여기에_위에서_만든_해시값');
