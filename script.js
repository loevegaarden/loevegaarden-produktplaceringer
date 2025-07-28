document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector("#productTable tbody");
    const searchInput = document.getElementById("searchInput");
    const updateButton = document.getElementById("update-json");

    function loadData() {
        fetch(loevegaardenPlaceringerData.jsonUrl)
            .then(res => res.json())
            .then(data => {
                tableBody.innerHTML = "";
                data.forEach(product => {
                    const row = document.createElement("tr");
                    row.innerHTML = `
                        <td>${product.id}</td>
                        <td>${product.title}</td>
                        <td>${product.gtin}</td>
                        <td>${product.placering}</td>
                        <td><a href="https://www.loevegaarden.dk/wp-admin/post.php?post=${product.id}&action=edit" target="_blank">Ret</a></td>
                    `;
                    tableBody.appendChild(row);
                });
            });
    }

    searchInput.addEventListener("input", () => {
        const query = searchInput.value.trim().toLowerCase();
        const rows = tableBody.querySelectorAll("tr");

        if (query.length < 3) {
            rows.forEach(row => row.style.display = "");
            return;
        }

        rows.forEach(row => {
            const gtin = row.children[2].textContent.toLowerCase();
            const title = row.children[1].textContent.toLowerCase();
            const placering = row.children[3].textContent.toLowerCase();
            row.style.display = (gtin.includes(query) || title.includes(query) || placering.includes(query)) ? "" : "none";
        });
    });

    if (updateButton) {
        updateButton.addEventListener("click", () => {
            updateButton.disabled = true;
            updateButton.textContent = "Opdaterer...";
            fetch(loevegaardenPlaceringerData.ajaxUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=loevegaarden_generate_json"
            })
            .then(res => res.json())
            .then(() => {
                loadData();
                updateButton.textContent = "Opdater liste";
                updateButton.disabled = false;
            });
        });
    }

    loadData();
});
document.getElementById('update-json').addEventListener('click', function () {
    fetch(loevegaardenPlaceringerData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'loevegaarden_generate_json'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.timestamp) {
            const updatedElem = document.querySelector('.updated-time strong');
            if (updatedElem) {
                updatedElem.textContent = data.data.timestamp.replace(' ', ' kl. ');
            }
        } else {
            alert('Noget gik galt under opdatering.');
        }
    });
});
