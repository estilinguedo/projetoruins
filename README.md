# Pacote de apresentação na escola

Leia COMECE-AQUI.md. No Ubuntu, com os requisitos instalados:

```bash
sudo bash INSTALAR-NA-ESCOLA.sh --com-demonstracao
```

Abra http://localhost/PlataformaJogadoresEscola/.

O instalador cria uma cópia em /var/www/PlataformaJogadoresEscola e um banco separado plataforma_jogadores_escola. As contas de demonstração são opcionais e suas senhas aleatórias são mostradas ao final. Não apaga registros existentes nem altera a autenticação do MariaDB.

O ZIP é voltado ao Apache do Ubuntu e não inclui servidor de desenvolvimento nem testes do Windows. A instalação manual e a migração dos dados antigos estão em LEIA-ME-UBUNTU.md. Veja também ARQUITETURA.md e INTEGRACAO_JOGO.md.

Integração Godot implementada: veja **INTEGRACAO_GODOT.md**. No cPanel, siga esse guia e importe a migração no banco existente; não execute o instalador Ubuntu da escola na hospedagem.
