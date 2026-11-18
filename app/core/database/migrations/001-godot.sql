-- Execute UMA VEZ no banco existente. Pode repetir: não exclui dados.
CREATE TABLE IF NOT EXISTS jogoacessos (
 tokenHash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 idUsuario BIGINT UNSIGNED NOT NULL,
 authVersion INT NOT NULL,
 expiraEm DATETIME NOT NULL,
 criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (idUsuario) REFERENCES usuarios(idUsuario) ON DELETE CASCADE,
 INDEX jogo_acessos_expiracao (expiraEm),
 INDEX jogo_acessos_usuario (idUsuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS jogocontrole (
 id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO jogocontrole (id) VALUES (1);
