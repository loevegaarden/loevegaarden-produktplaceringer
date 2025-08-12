jQuery(function($){
    let dataList=[];
    const placements=lgppData.placements;
    let searchTimeout;
    $(document).ready(()=>$('#searchInput').focus());
    function showOverlay(){ $('#lgpp-overlay').removeClass('hidden'); }
    function hideOverlay(){ $('#lgpp-overlay').addClass('hidden'); }
    function loadJSON(){ showOverlay(); $.getJSON(`${lgppData.jsonUrl}?${Date.now()}`)
        .done(json=>{ dataList=json; render(); hideOverlay(); })
        .fail(()=>{alert('Kunne ikke hente listen'); hideOverlay();}); }
    function render(){ const q=$('#searchInput').val().trim().toLowerCase();
        if(q.length<4){ return $('#productTable tbody').html('<tr><td colspan="10" style="text-align:center">Indtast mindst 4 tegn for at søge</td></tr>'); }
        let html=''; dataList.forEach(item=>{
            if(item.title.toLowerCase().includes(q)||item.gtin.includes(q)||item.placering.toLowerCase().includes(q)){
                let current=''; if(item.expiry_enabled){ current=item.current_stock.map(e=>{const [n,dt]=e.split('@');[y,m,d]=dt.split(' ')[0].split('-');return`${n} stk, BF: ${d}-${m}-${y}`;}).join('<br>'); } else current=item.current_stock;
                html+=`<tr data-id="${item.id}">`+
                    `<td>${item.id}</td><td>${item.title}</td><td>${item.gtin||''}</td>`+
                    `<td><select class="placement-select">${placements.map(p=>`<option value="${p}"${p===item.placering?' selected':''}>${p}</option>`).join('')}</select></td>`+
                    `<td><input type="checkbox" class="enable-expiry-toggle"${item.expiry_enabled?' checked':''}></td>`+
                    `<td>${current}</td><td>${item.open_orders}</td>`+
                    `<td><input type="number" class="expiry-qty" min="0"></td>`+
                    `<td><input type="date" class="expiry-date"${item.expiry_enabled?'':' disabled'}></td>`+
                    `<td><button class="save-expiry button">💾</button></td></tr>`;
            }
        });
        $('#productTable tbody').html(html||'<tr><td colspan="10" style="text-align:center">Ingen varer fundet</td></tr>');
    }
    $('#searchInput').on('input',()=>{clearTimeout(searchTimeout);searchTimeout=setTimeout(render,300);});
    $('#update-json').on('click', function(){
        showOverlay();
        $.post(lgppData.ajaxUrl, { action:'loevegaarden_generate_json', nonce: lgppData.nonceGen })
         .done(function(res){
            if(res && res.success){ if(res.data && res.data.updated_at){ $('#json-updated-at').text(res.data.updated_at); } loadJSON(); }
            else { alert('Kunne ikke opdatere JSON'); hideOverlay(); }
         })
         .fail(function(xhr){ alert('JSON-opdatering fejlede: '+(xhr.responseText||xhr.statusText)); hideOverlay(); });
    });
    $(document).on('change','.enable-expiry-toggle',function(){ $(this).closest('tr').find('.expiry-date').prop('disabled',!this.checked); });
    $(document).on('click','.save-expiry',function(){ const r=$(this).closest('tr'),
        id=r.data('id'),qty=parseInt(r.find('.expiry-qty').val())||0,date=r.find('.expiry-date').val(),
        place=r.find('.placement-select').val(),enabled=r.find('.enable-expiry-toggle').is(':checked')?'yes':'no';
        $.post(lgppData.ajaxUrl,{action:'loevegaarden_save_position_data',post_id:id,quantity:qty,date:date,placement:place,expiry_enabled:enabled,nonce:lgppData.nonceSave},function(res){
            if(res && res.success){
                r.css('background','#e0ffe0');
                if(res.data && res.data.message){ console.log(res.data.message); }
            } else {
                const msg = res && res.data && res.data.message ? res.data.message : 'Der opstod en fejl under gem. Kontroller felterne og prøv igen.';
                alert(msg);
            }
        }).fail(function(xhr){
            alert('Uventet fejl: ' + (xhr.responseText||xhr.statusText));
        });
    });
    loadJSON();
});