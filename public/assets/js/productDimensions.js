document.addEventListener('DOMContentLoaded', function() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    function showLoading() {
        loadingOverlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    function hideLoading() {
        loadingOverlay.style.display = 'none';
        document.body.style.overflow = '';
    }
    var successToast = new bootstrap.Toast(document.getElementById('successToast'), {
        delay: 3000
    });
    document.querySelectorAll('.pagination .page-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('page', page);
            window.location.href = currentUrl.toString();
        });
    });
    document.querySelectorAll('th.sortable').forEach(function(header) {
        header.addEventListener('click', function() {
            const table = document.getElementById('productTable');
            const thIndex = Array.from(this.parentElement.children).indexOf(this);
            const isHidden = Array.from(this.classList).includes('d-none');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const sortDirection = this.classList.contains('asc') ? -1 : 1;
            document.querySelectorAll('.sortable').forEach(function(h) {
                h.classList.remove('asc', 'desc');
            });
            this.classList.add(sortDirection === 1 ? 'asc' : 'desc');
            rows.sort(function(rowA, rowB) {
                const cellA = rowA.cells[thIndex];
                const cellB = rowB.cells[thIndex];
                const valueA = cellA.querySelector('input') ?
                    cellA.querySelector('input').value :
                    cellA.textContent.trim();
                const valueB = cellB.querySelector('input') ?
                    cellB.querySelector('input').value :
                    cellB.textContent.trim();
                if (!isNaN(valueA) && !isNaN(valueB)) {
                    return (parseFloat(valueA) - parseFloat(valueB)) * sortDirection;
                } else {
                    return valueA.toString().localeCompare(valueB.toString()) * sortDirection;
                }
            });
            const tbody = table.querySelector('tbody');
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
        });
    });
    document.querySelectorAll('.editable-field').forEach(function(input) {
        const originalValue = input.value;

        input.addEventListener('change', function() {
            if (this.value !== originalValue) {
                this.classList.add('changed');
            } else {
                this.classList.remove('changed');
            }
        });
    });
    document.querySelectorAll('.save-dimensions').forEach(function(button) {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const productId = row.getAttribute('data-product-id');
            const updatedData = {id: productId};
            let hasChanges = false;
            row.querySelectorAll('.editable-field').forEach(function(input) {
                if (input.classList.contains('changed')) {
                    const fieldName = input.getAttribute('data-field');
                    updatedData[fieldName] = input.value;
                    hasChanges = true;
                }
            });
            if (hasChanges) {
                showLoading();
                fetch('/api/updateProductDimensions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(updatedData)
                })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if(data.success) {
                            successToast.show();
                            row.querySelectorAll('.editable-field.changed').forEach(function(input) {
                                input.classList.remove('changed');
                            });
                        } else {
                            alert('Hata oluştu: ' + data.message);
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        alert('İşlem sırasında bir hata oluştu.');
                    });
            } else {
                alert('Değişiklik yapılmadı.');
            }
        });
    });
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('page');
        const formData = new FormData(this);
        for (const [key, value] of formData.entries()) {
            if (value) {
                currentUrl.searchParams.set(key, value);
            } else {
                currentUrl.searchParams.delete(key);
            }
        }
        window.location.href = currentUrl.toString();
    });
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('page');
        const searchValue = document.getElementById('globalSearch').value;
        if (searchValue) {
            currentUrl.searchParams.set('search', searchValue);
        } else {
            currentUrl.searchParams.delete('search');
        }
        window.location.href = currentUrl.toString();
    });
});