jQuery(function($){
  let dataList=[];
  const placements=lgppData.placements;
  let searchTimeout;
  // Registrer kun placeringer-mode
  let placementsOnly=false;
  let pendingProductId=null;

  $(document).ready(()=>$('#searchInput').focus());

  function showOverlay(){ $('#lgpp-overlay').removeClass('hidden'); }
  function hideOverlay(){ $('#lgpp-overlay').addClass('hidden'); }

  function loadJSON(){
    showOverlay();
    $.getJSON(`${lgppData.jsonUrl}?${Date.now()}`)
      .done(json=>{ dataList=json; render(); })
      .fail(()=>{ alert('Kunne ikke hente listen'); })
      .always(()=>{ hideOverlay(); });
  }

  function render(){
    const q=$('#searchInput').val().trim().toLowerCase();
    if(q.length<4){
      $('#productTable tbody').html('<tr><td colspan="10" style="text-align:center">Indtast mindst 4 tegn for at søge</td></tr>');
      return;
    }
    let html='';
    dataList.forEach(item=>{
      if(item.title.toLowerCase().includes(q) || (item.gtin||'').includes(q) || (item.placering||'').toLowerCase().includes(q)){
        let current='';
        if(item.expiry_enabled){
          current=(item.current_stock||[]).map(e=>{
            const [n,dt]=e.split('@');
            const dstr=(dt||'').split(' ')[0]||'';
            const [y,m,d]=dstr.split('-');
            return `${n} stk, BF: ${d}-${m}-${y}`;
          }).join('<br>');
        } else {
          current=item.current_stock;
        }
        html+=`<tr data-id="${item.id}">`
             + `<td>${item.id}</td><td>${item.title}</td><td>${item.gtin||''}</td>`
             + `<td><select class="placement-select">${placements.map(p=>`<option value="${p}"${p===item.placering?' selected':''}>${p}</option>`).join('')}</select></td>`
             + `<td><input type="checkbox" class="enable-expiry-toggle"${item.expiry_enabled?' checked':''}></td>`
             + `<td>${current}</td><td>${item.open_orders}</td>`
             + `<td><input type="number" class="expiry-qty" min="0"></td>`
             + `<td><input type="date" class="expiry-date"${item.expiry_enabled?'':' disabled'}></td>`
             + `<td><button class="save-expiry button">💾</button></td>`
             + `</tr>`;
      }
    });
    $('#productTable tbody').html(html||'<tr><td colspan="10" style="text-align:center">Ingen varer fundet</td></tr>');
  }

  $('#searchInput').on('input', ()=>{ clearTimeout(searchTimeout); searchTimeout=setTimeout(render,300); });

  // Trin 1: scan VARER i søgefeltet når "Registrer kun placeringer" er slået til
  $('#searchInput').on('keydown', function(e){
    if(e.key!=="Enter") return;
    e.preventDefault();
    const code=$(this).val().trim();
    if(!placementsOnly){ render(); return; }
    if(!code) return;
    const item = dataList.find(x => (x.gtin && x.gtin===code) || String(x.id)===code || x.title.toLowerCase()===code.toLowerCase());
    if(item){
      pendingProductId=item.id;
      $('#placement-product-title').text(item.title);
      $('#placement-status').removeClass('hidden');
      render();
      $('#placementScan').removeClass('hidden').val('').focus();
    } else {
      alert('Vare ikke fundet. Skan varen igen.');
    }
  });

  // Trin 2: scan PLACERING i separat felt
  $('#placementScan').on('keydown', function(e){
    if(e.key!=="Enter") return;
    e.preventDefault();
    const code=$(this).val().trim();
    if(!placementsOnly) return;
    if(!pendingProductId){ alert('Ingen vare valgt. Skan varen først.'); $('#searchInput').focus(); return; }
    const row=$(`#productTable tbody tr[data-id="${pendingProductId}"]`);
    if(!row.length){ alert('Varen er ikke i listen. Skan varen igen.'); pendingProductId=null; $('#searchInput').focus(); return; }
    const sel=row.find('.placement-select');
    const opts=sel.find('option').map((i,o)=>o.value).get();
    const val=opts.find(v=>v.toLowerCase()===code.toLowerCase());
    if(!val){ alert('Placering ikke fundet: '+code); $(this).val(''); return; }
    sel.val(val);
    const enabled=row.find('.enable-expiry-toggle').is(':checked')?'yes':'no';
    $.post(lgppData.ajaxUrl,{action:'loevegaarden_save_position_data',post_id:pendingProductId,quantity:0,date:'',placement:val,expiry_enabled:enabled,nonce:lgppData.nonceSave})
      .done(res=>{
        if(res && res.success){ row.css('background','#e0ffe0'); }
        else { const msg=res && res.data && res.data.message?res.data.message:'Fejl under gem af placering.'; alert(msg); }
      })
      .fail(xhr=>{ alert('Uventet fejl: '+(xhr.responseText||xhr.statusText)); })
      .always(()=>{
        pendingProductId=null;
        $('#placement-product-title').text('');
        $('#placement-status').addClass('hidden');
        $('#placementScan').addClass('hidden').val('');
        $('#searchInput').val('').focus();
      });
  });

  // Toggle for 'Registrer kun placeringer'
  $(document).on('change','#placements-only',function(){
    placementsOnly=this.checked;
    pendingProductId=null;
    $('#placement-product-title').text('');
    $('#placement-status').toggleClass('hidden', !placementsOnly);
    $('#placementScan').toggleClass('hidden', !placementsOnly).val('');
    $('#searchInput').val('').focus();
  });

  // Opdater JSON-knap
  $('#update-json').on('click', function(){
    showOverlay();
    $.post(lgppData.ajaxUrl, { action:'loevegaarden_generate_json', nonce: lgppData.nonceGen })
      .done(res=>{
        if(res && res.success){
          if(res.data && res.data.updated_at){ $('#json-updated-at').text(res.data.updated_at); }
          loadJSON();
        } else {
          alert('Kunne ikke opdatere JSON');
          hideOverlay();
        }
      })
      .fail(xhr=>{ alert('JSON-opdatering fejlede: '+(xhr.responseText||xhr.statusText)); hideOverlay(); });
  });

  // UI interaktioner
  $(document).on('change','.enable-expiry-toggle',function(){
    $(this).closest('tr').find('.expiry-date').prop('disabled', !this.checked);
  });

  $(document).on('click','.save-expiry',function(){
    const r=$(this).closest('tr');
    const id=r.data('id');
    const qty=parseInt(r.find('.expiry-qty').val())||0;
    const date=r.find('.expiry-date').val();
    const place=r.find('.placement-select').val();
    const enabled=r.find('.enable-expiry-toggle').is(':checked')?'yes':'no';
    $.post(lgppData.ajaxUrl,{action:'loevegaarden_save_position_data',post_id:id,quantity:qty,date:date,placement:place,expiry_enabled:enabled,nonce:lgppData.nonceSave})
      .done(res=>{
        if(res && res.success){
          r.css('background','#e0ffe0');
          if(res.data && res.data.message){ console.log(res.data.message); }
        } else {
          const msg = res && res.data && res.data.message ? res.data.message : 'Der opstod en fejl under gem. Kontroller felterne og prøv igen.';
          alert(msg);
        }
      })
      .fail(xhr=>{ alert('Uventet fejl: ' + (xhr.responseText||xhr.statusText)); });
  });

  // Første load
  loadJSON();
});