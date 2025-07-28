jQuery(document).ready(function ($) {
    let productData = [];

    function renderTable(data) {
        const tbody = $('#productTable tbody');
        tbody.empty();

        data.forEach(product => {
            const row = $('<tr></tr>');

            row.append(`<td>${product.id}</td>`);
            row.append(`<td>${product.title}</td>`);
            row.append(`<td>${product.gtin || ''}</td>`);
            row.append(`<td>${product.placering || ''}</td>`);

            const dateInput = $('<input type="text" class="datepicker" size="10">');
            const qtyInput = $('<input type="number" min="1" size="4">');
            const saveBtn = $('<button class="button">Gem</button>');
            const status = $('<span class="status"></span>');

            dateInput.addClass('hasDatepicker').datepicker({ dateFormat: 'yy-mm-dd' });

            saveBtn.on('click', function () {
                const expiryDate = dateInput.val();
                const quantity = qtyInput.val();

                if (!expiryDate || !quantity) {
                    status.text('⚠️');
                    return;
                }

                $.post(loevegaardenPlaceringerData.ajaxUrl, {
                    action: 'loevegaarden_save_expiry_data',
                    product_id: product.id,
                    expiry_date: expiryDate,
                    quantity: quantity
                }, function (response) {
                    if (response.success) {
                        status.text('✔️');
                        dateInput.val('');
                        qtyInput.val('');
                    } else {
                        status.text('❌');
                    }
                });
            });

            row.append($('<td></td>').append(dateInput));
            row.append($('<td></td>').append(qtyInput));
            row.append($('<td></td>').append(saveBtn).append(status));

            tbody.append(row);
        });
    }

    function filterTable() {
        const query = $('#searchInput').val().toLowerCase();
        if (query.length < 3) {
            renderTable(productData);
            return;
        }

        const filtered = productData.filter(product =>
            product.title.toLowerCase().includes(query) ||
            (product.gtin && product.gtin.toLowerCase().includes(query)) ||
            (product.placering && product.placering.toLowerCase().includes(query))
        );

        renderTable(filtered);
    }

    $('#searchInput').on('input', filterTable);

    $('#update-json').on('click', function () {
        $.post(loevegaardenPlaceringerData.ajaxUrl, {
            action: 'loevegaarden_generate_json'
        }, function (response) {
            if (response.success) {
                location.reload();
            }
        });
    });

    $.getJSON(loevegaardenPlaceringerData.jsonUrl, function (data) {
        productData = data;
        renderTable(productData);
    });
});
