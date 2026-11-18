/* Frontend: apenas DOM e AJAX JSON. Banco e SMTP ficam nos Models PHP. */
(() => {
  'use strict';
  const base = new URL('../../', document.currentScript.src);
  const page = document.body.dataset.pagina;
  const query = new URLSearchParams(location.search);
  let csrf = '', user = null;
  const protectedPages = ['leaderboard','perfil','moderacao','historico','confirmar-remocao'];
  const moderatorPages = ['moderacao','historico','confirmar-remocao'];
  function show(selector, visible) { $(selector).prop('hidden', !visible); }
  function message(text, error = false) {
    $('#message').text(text).removeClass('alert-success alert-danger').addClass(error ? 'alert-danger' : 'alert-success');
    show('#message', true); $('#message').trigger('focus');
  }
  function go(path, text) {
    if (text) sessionStorage.setItem('notice', text);
    location.assign(new URL(path, base).href);
  }
  async function api(path, method = 'GET', data = {}) {
    try {
      const result = await $.ajax({url: new URL('api/' + path, base).href, method, dataType: 'json',
        ...(method === 'GET' ? {data} : {contentType: 'application/json; charset=utf-8', data: JSON.stringify(data), headers: {'X-CSRF-Token': csrf}})});
      csrf = result.csrf || csrf;
      return result.data;
    } catch (xhr) {
      csrf = xhr.responseJSON?.csrf || csrf;
      if (xhr.status === 401) go('login.html', 'Sua sessão terminou. Entre novamente.');
      throw new Error(xhr.responseJSON?.message || 'Não foi possível conectar ao site. Confira o Apache e o endereço.');
    }
  }
  function fields(form) { return Object.fromEntries($(form).serializeArray().map(x => [x.name,x.value])); }
  function bind(selector, handler) {
    $(selector).on('submit', async function(event) {
      event.preventDefault(); if ($(this).data('busy')) return;
      $(this).data('busy',true).find('button[type="submit"]').prop('disabled',true);
      try { await handler(this); } catch(error) { message(error.message,true); }
      finally { $(this).data('busy',false).find('button[type="submit"]').prop('disabled',false); }
    });
  }
  function pagination(data) {
    $('#page-number').text('Página ' + data.page);
    for (const [id,number,visible] of [['previous',data.page-1,data.page>1],['next',data.page+1,data.hasNext]]) {
      const params = new URLSearchParams(location.search);params.set('pagina',number);
      $('#' + id).attr('href',page+'.html?'+params);show('#'+id,visible);
    }
  }
  function cell(row,text) { return $('<td>').text(text).appendTo(row); }
  function profileLink(id,text) { return $('<a>').attr('href','perfil.html?id='+encodeURIComponent(id)).text(text); }
  async function photo(id, img) {
    try { const data = await api('foto','GET',{id});$(img).attr('src',data.image);show(img,true); }
    catch(error) { $(img).replaceWith($('<span>').text('Foto indisponível')); }
  }
  async function list() {
    const endpoint = page==='historico' ? 'moderacao/historico' : page;
    const data = await api(endpoint,'GET',{pagina:query.get('pagina') || 1,busca:query.get('busca') || ''});
    const tbody = $('#rows').empty();
    if (!data.rows.length) $('<tr>').append($('<td>').attr('colspan',page==='historico'?5:4).text('Nenhum resultado disponível nesta página.')).appendTo(tbody);
    for (const item of data.rows) {
      const tr = $('<tr>').appendTo(tbody);
      if (page==='leaderboard') {
        cell(tr,item.posicao);const pic = cell(tr,'Sem foto');
        if (item.idFoto) { const img=$('<img>').attr({width:48,height:48,alt:'Perfil de '+item.nomeUsuario,loading:'lazy',hidden:true});pic.empty().append(profileLink(item.idUsuario,'').append(img));void photo(item.idUsuario,img); }
        cell(tr,'').append(profileLink(item.idUsuario,item.nomeUsuario));cell(tr,item.pontuacao);
      } else if (page==='moderacao') {
        cell(tr,item.nomeUsuario);cell(tr,item.idFoto?'Sim':'Não');cell(tr,item.descricao);cell(tr,'').append(profileLink(item.idUsuario,'Revisar perfil'));
      } else for (const key of ['criadoEm','moderador','nomeUsuario','tipo','motivo']) cell(tr,item[key]);
    }
    pagination(data);
  }
  async function profile() {
    const data = await api('perfil','GET',query.has('id') ? {id:query.get('id')} : {}), p=data.profile;
    $('h1').text('Perfil de '+p.nomeUsuario);
    $('#profile-description').text(p.descricao || 'Este jogador ainda não escreveu uma descrição.');
    $('#profile-ranking').text(data.ranking ? 'Posição: '+data.ranking.posicao+'. Pontuação: '+data.ranking.pontuacao+'.' : 'Este jogador ainda não possui resultados no ranking.');
    show('#no-photo',!p.idFoto);show('#profile-photo',false);if(p.idFoto) await photo(p.idUsuario,'#profile-photo');
    show('#own-description, #own-photo',data.own);$('#descricao').val(p.descricao);
    show('#photo-form',!p.idFoto);show('#photo-exists',!!p.idFoto);
    show('#moderate-profile',user.papel==='moderador');
    for (const [id,type,visible] of [['remove-photo','foto',!!p.idFoto],['remove-description','descricao',!!p.descricao]]) {
      show('#'+id,visible);$('#'+id+' a').attr('href','confirmar-remocao.html?id='+p.idUsuario+'&tipo='+type);
    }
  }
  async function preview() {
    const data = await api('moderacao/remover','GET',{id:query.get('id'),tipo:query.get('tipo')}), p=data.profile;
    $('#removal-question').text('Remover '+(data.type==='foto'?'a foto':'a descrição')+' de '+p.nomeUsuario+'?');
    if(data.type==='foto') await photo(p.idUsuario,'#removal-photo');
    else {$('#removal-description').text(p.descricao);show('#removal-description',true);}
    for (const [key,val] of Object.entries({id:p.idUsuario,tipo:data.type,expected:data.expected})) $('[name="'+key+'"]').val(val);
    $('#cancel-removal').attr('href','perfil.html?id='+p.idUsuario);
  }
  $(async () => {
    try {
      const session=await api('session');user=session.user;
      if(protectedPages.includes(page) && !user) {go('login.html','Entre na conta para acessar esta página.');return;}
      if(moderatorPages.includes(page) && user.papel!=='moderador') throw new Error('Permissão de moderação necessária.');
      show('[data-guest]',!user);show('[data-user]',!!user);show('[data-moderator]',user?.papel==='moderador');
      bind('#logout-form',async()=>{await api('sair','POST');go('login.html');});
      $('[name="token"]').val(query.get('token') || '');
      bind('#main-form',async form=>{
        const endpoint=$(form).data('endpoint'), data=fields(form), result=await api(endpoint,'POST',data);
        go(endpoint==='login'?'leaderboard.html':endpoint==='moderacao/remover'?'perfil.html?id='+encodeURIComponent(data.id):'login.html',result.message);
      });
      bind('#description-form',async form=>{const result=await api('perfil/descricao','POST',fields(form));await profile();message(result.message);});
      bind('#photo-form',async form=>{
        const file=form.elements.foto.files[0];if(!file || file.size>5242880) throw new Error('Selecione uma imagem de até 5 MB.');
        const encoded=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result.split(',')[1]);reader.onerror=()=>reject(new Error('Não foi possível ler a imagem.'));reader.readAsDataURL(file);});
        const result=await api('perfil/foto','POST',{foto:encoded});await profile();message(result.message);
      });
      $('#busca').val(query.get('busca') || '');bind('#search-form',async form=>{go('moderacao.html?busca='+encodeURIComponent(fields(form).busca));});
      if(['leaderboard','moderacao','historico'].includes(page)) await list();
      if(page==='perfil') await profile();
      if(page==='confirmar-remocao') await preview();
      show('#page-content',true);
      const notice=sessionStorage.getItem('notice');if(notice){sessionStorage.removeItem('notice');message(notice);}
    } catch(error) {message(error.message,true);}
    finally {show('#loading',false);}
  });
})();
