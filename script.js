jQuery(document).ready(function ($) {
    let productData = [];

    // Indlæs JSON men vis ikke noget i starten
    $.getJSON(loevegaardenPlaceringerData.jsonUrl, function (data) {
        productData = data;
        $('#productTable tbody').html('<tr><td colspan="7" style="text-align:center;">Søg i søgefeltet for at få varer frem</td></tr>');
    });

    // Håndter søgning
    $('#searchInput').on('input', function () {
        filterTable();
    });

    function filterTable() {
        const query = $('#searchInput').val().toLowerCase();
        const tbody = $('#productTable tbody');

        if (query.length < 3) {
            tbody.html('<tr><td colspan="7" style="text-align:center;">Søg i søgefeltet for at få varer frem</td></tr>');
            return;
        }

        const filtered = productData.filter(product =>
            product.title.toLowerCase().includes(query) ||
            (product.gtin && product.gtin.toLowerCase().includes(query)) ||
            (product.placering && product.placering.toLowerCase().includes(query))
        );

        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="7" style="text-align:center;">Ingen resultater</td></tr>');
        } else {
            renderTable(filtered);
        }
    }

    function renderTable(data) {
        const tbody = $('#productTable tbody');
        tbody.empty();
        data.forEach(product => {
            const row = $(`
                <tr>
                    <td>${product.id}</td>
                    <td>${product.title}</td>
                    <td>${product.gtin}</td>
                    <td>${product.placering}</td>
                    <td><input type="date" class="expiry-date" data-id="${product.id}"></td>
                    <td><input type="number" class="expiry-qty" data-id="${product.id}" min="0" step="1"></td>
                    <td><button class="save-expiry button" data-id="${product.id}">Gem</button><span class="status" style="margin-left: 10px;"></span></td>
                </tr>
            `);
            tbody.append(row);
        });
    }

    // Håndter klik på "Gem"-knapper
    $('#productTable').on('click', '.save-expiry', function () {
        const productId = $(this).data('id');
        const expiryDate = $(`.expiry-date[data-id="${productId}"]`).val();
        const quantity = parseInt($(`.expiry-qty[data-id="${productId}"]`).val(), 10);
        const status = $(this).siblings('.status');

        if (!expiryDate || isNaN(quantity) || quantity <= 0) {
            status.text('⚠️ Udfyld dato og antal');
            return;
        }

        $.post(loevegaardenPlaceringerData.ajaxUrl, {
            action: 'loevegaarden_save_expiry_data',
            product_id: productId,
            expiry_date: expiryDate,
            quantity: quantity
        }, function (response) {
            if (response.success) {
                status.text('✔️');
                $(`.expiry-date[data-id="${productId}"]`).val('');
                $(`.expiry-qty[data-id="${productId}"]`).val('');
            } else {
                status.text('❌ Fejl');
            }
        });
    });

    // Håndter klik på “Opdater liste”-knappen
    $('#update-json').on('click', function () {
        $('#productTable tbody').html('<tr><td colspan="7" style="text-align:center;">🔄 Indlæser...</td></tr>');

        $.post(loevegaardenPlaceringerData.ajaxUrl, {
            action: 'loevegaarden_generate_json'
        }, function (response) {
            if (response.success) {
                location.reload();
            }
        });
    });
});