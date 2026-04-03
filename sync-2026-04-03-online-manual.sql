-- Mudancas de banco da sincronizacao do modulo On-Line/chat.
-- Execute este script manualmente no banco da aplicacao.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS tb21_usuarios_online (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    active_role TINYINT UNSIGNED NOT NULL,
    active_unit_id BIGINT UNSIGNED NULL,
    last_seen_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tb21_usuarios_online_session_id_unique (session_id),
    KEY tb21_usuarios_online_last_seen_at_index (last_seen_at),
    KEY tb21_usuarios_online_user_id_last_seen_at_index (user_id, last_seen_at),
    KEY tb21_usuarios_online_active_unit_id_foreign (active_unit_id),
    CONSTRAINT tb21_usuarios_online_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT tb21_usuarios_online_active_unit_id_foreign
        FOREIGN KEY (active_unit_id) REFERENCES tb2_unidades (tb2_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb22_chat_mensagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,
    sender_role TINYINT UNSIGNED NOT NULL,
    sender_unit_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY tb22_chat_mensagens_sender_id_recipient_id_index (sender_id, recipient_id),
    KEY tb22_chat_mensagens_recipient_id_read_at_index (recipient_id, read_at),
    KEY tb22_chat_mensagens_sender_unit_id_foreign (sender_unit_id),
    CONSTRAINT tb22_chat_mensagens_sender_id_foreign
        FOREIGN KEY (sender_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT tb22_chat_mensagens_recipient_id_foreign
        FOREIGN KEY (recipient_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT tb22_chat_mensagens_sender_unit_id_foreign
        FOREIGN KEY (sender_unit_id) REFERENCES tb2_unidades (tb2_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
