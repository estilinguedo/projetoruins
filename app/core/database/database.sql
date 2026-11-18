-- Instalação NOVA. Não apaga bancos nem tabelas existentes.
-- Importe no banco plataforma_jogadores criado previamente.
CREATE TABLE usuarios (
 idUsuario BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nomeUsuario VARCHAR(30) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 senha VARCHAR(255) NOT NULL,
 descricao VARCHAR(200) NOT NULL DEFAULT '',
 ativacao CHAR(1) NOT NULL DEFAULT 'N',
 papel ENUM('usuario','moderador') NOT NULL DEFAULT 'usuario',
 authVersion INT NOT NULL DEFAULT 1,
 criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 dataAtivacao DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE tokensconta (
 idToken BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 idUsuario BIGINT UNSIGNED NOT NULL,
 tipo ENUM('ativacao','senha') NOT NULL,
 tokenHash CHAR(64) NOT NULL UNIQUE,
 expiraEm DATETIME NOT NULL,
 usadoEm DATETIME NULL,
 criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (idUsuario) REFERENCES usuarios(idUsuario) ON DELETE CASCADE,
 INDEX tokens_usuario_tipo (idUsuario,tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE fotosperfil (
 idFoto BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 idUsuario BIGINT UNSIGNED NOT NULL UNIQUE,
 nomeArmazenado VARCHAR(80) NOT NULL UNIQUE,
 mimeType VARCHAR(30) NOT NULL,
 tamanhoBytes INT UNSIGNED NOT NULL,
 criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (idUsuario) REFERENCES usuarios(idUsuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE acoesmoderacao (
 idAcao BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 idModerador BIGINT UNSIGNED NOT NULL,
 idUsuario BIGINT UNSIGNED NOT NULL,
 tipo VARCHAR(30) NOT NULL,
 motivo VARCHAR(200) NOT NULL,
 criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (idModerador) REFERENCES usuarios(idUsuario),
 FOREIGN KEY (idUsuario) REFERENCES usuarios(idUsuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- O jogo publica a posição FINAL. O site não define pontuação ou desempate.
CREATE TABLE leaderboardresultados (
 idUsuario BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 posicao INT UNSIGNED NOT NULL,
 pontuacao VARCHAR(40) NOT NULL,
 atualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (idUsuario) REFERENCES usuarios(idUsuario) ON DELETE CASCADE,
 INDEX ranking_posicao (posicao,idUsuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
