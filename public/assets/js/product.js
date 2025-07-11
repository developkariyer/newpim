
window.csrfToken = '{{ csrf_token }}';
'use strict';

/**
 * Ana Ürün Form Yöneticisi
 */
class ProductFormManager {
    constructor() {
        this.state = {
            selectedProduct: null,
            isEditMode: false,
            lockedVariants: [],
            selectedItems: {
                colors: [],
                brands: [],
                marketplaces: [],
                categories: []
            },
            searchTimeout: null,
            currentStep: 1
        };

        this.config = {
            searchDelay: 300,
            minSearchLength: 2,
            endpoints: {
                productSearch: '/product/search-products',
                itemSearch: '/product/search',
                addColor: '/product/add-color',
                deleteVariant: '/product/delete-variant'
            },
            icons: {
                colors: '🎨',
                brands: '🏷️',
                marketplaces: '🛒',
                categories: '📂'
            },
            typeLabels: {
                colors: 'renkler',
                brands: 'markalar',
                marketplaces: 'pazaryerleri',
                categories: 'kategori'
            },
            inputNames: {
                colors: 'colors[]',
                brands: 'brands[]',
                marketplaces: 'marketplaces[]',
                categories: 'productCategory'
            }
        };

        this.searchService = new SearchService(this.config);
        this.formService = new FormService(this.config, this.state);
        this.tableService = new TableService();
        this.variationService = new VariationService(this.state);
        this.validationService = new ValidationService();
        this.uiService = new UIService();

        this.initialize();
        this.checkForEditProduct();
    }

    initialize() {
        try {
            this.bindEvents();
            this.validateEnvironment();
            console.log('✅ ProductFormManager initialized successfully');
        } catch (error) {
            console.error('❌ ProductFormManager initialization failed:', error);
            this.uiService.showError('Uygulama başlatılamadı. Lütfen sayfayı yenileyin.');
        }
    }

    bindEvents() {
        this.bindProductSearchEvents();
        this.bindFormEvents();
        this.bindSelectionEvents();
        this.bindNavigationEvents();
        this.bindTableEvents();
        this.bindVariantEvents();
    }

    bindProductSearchEvents() {
        const searchInput = this.getElement('productSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(
                (e) => this.handleProductSearch(e.target.value),
                this.config.searchDelay
            ));
        }

        const clearButton = this.getElement('clearSelectedProduct');
        if (clearButton) {
            clearButton.addEventListener('click', () => this.clearForm());
        }
    }

    bindFormEvents() {
        const form = this.getElement('productForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
        }

        const imageInput = this.getElement('imageInput');
        if (imageInput) {
            imageInput.addEventListener('change', (e) => this.handleImageUpload(e));
        }

        const addColorBtn = this.getElement('addNewColorBtn');
        if (addColorBtn) {
            addColorBtn.addEventListener('click', () => this.handleAddNewColor());
        }
    }

    bindSelectionEvents() {
        // Searchable items (colors)
        const colorsSearch = this.getElement('colorsSearch');
        if (colorsSearch) {
            colorsSearch.addEventListener('input', this.debounce(
                () => this.handleItemSearch('colors'),
                this.config.searchDelay
            ));
        }

        // Selectable items (categories, brands, marketplaces)
        ['categories', 'brands', 'marketplaces'].forEach(type => {
            const selectElement = this.getElement(`${type}Search`);
            if (selectElement) {
                selectElement.addEventListener('change', (e) => this.handleItemSelect(type, e));
            }
        });
    }

    bindNavigationEvents() {
        const goToVariationsBtn = this.getElement('goToVariationsBtn');
        if (goToVariationsBtn) {
            goToVariationsBtn.addEventListener('click', () => this.handleGoToVariations());
        }

        const backToStep1Btn = this.getElement('backToStep1Btn');
        if (backToStep1Btn) {
            backToStep1Btn.addEventListener('click', () => this.showStep(1));
        }
    }

    bindTableEvents() {
        const tableButtons = [
            { id: 'addSizeRowBtn', handler: () => this.tableService.addSizeRow() },
            { id: 'addCustomRowBtn', handler: () => this.tableService.addCustomRow() },
            { id: 'saveSizeTableBtn', handler: () => this.tableService.saveSizeTable() },
            { id: 'saveCustomTableBtn', handler: () => this.tableService.saveCustomTable() }
        ];

        tableButtons.forEach(({ id, handler }) => {
            const element = this.getElement(id);
            if (element) {
                element.addEventListener('click', handler);
            }
        });

        this.bindTableRowDeletion('sizeTable');
        this.bindTableRowDeletion('customTable');
    }

    bindTableRowDeletion(tableId) {
        const table = this.getElement(tableId);
        if (table) {
            const tbody = table.querySelector('tbody');
            if (tbody) {
                tbody.addEventListener('click', (e) => {
                    if (e.target.classList.contains('remove-row-btn') &&
                        !e.target.closest('tr').classList.contains('locked-row')) {
                        e.target.closest('tr').remove();
                    }
                });
            }
        }
    }

    bindVariantEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-variant-btn')) {
                this.handleDeleteVariant(e.target);
            }
        });
    }

    // Product Search Methods
    async handleProductSearch(query) {
        if (query.length < this.config.minSearchLength) {
            this.uiService.hideElement('productSearchResults');
            return;
        }

        try {
            const results = await this.searchService.searchProducts(query);
            this.displayProductSearchResults(results);
        } catch (error) {
            console.error('Product search failed:', error);
            this.uiService.showError('Ürün arama başarısız.');
        }
    }

    displayProductSearchResults(items) {
        const resultsDiv = this.getElement('productSearchResults');
        if (!resultsDiv) return;

        if (items.length === 0) {
            resultsDiv.innerHTML = '<div style="padding: 1rem; text-align: center; color: #6c757d;">Ürün bulunamadı</div>';
            this.uiService.showElement('productSearchResults');
            return;
        }

        resultsDiv.innerHTML = items.map(item => `
            <div class="search-result-item" data-product-id="${item.id}" style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f1f3f4;">
                <div style="font-weight: 500;">${this.escapeHtml(item.name)}</div>
                <div style="font-size: 0.875rem; color: #6c757d;">${this.escapeHtml(item.productIdentifier)}</div>
                <div style="font-size: 0.75rem; color: #28a745;">${item.hasVariants ? '✅ Varyantları var' : '⚠️ Varyant yok'}</div>
            </div>
        `).join('');

        resultsDiv.addEventListener('click', (e) => {
            const resultItem = e.target.closest('.search-result-item');
            if (resultItem) {
                const productId = resultItem.dataset.productId;
                const product = items.find(item => item.id == productId);
                if (product) {
                    this.selectProductForEdit(product);
                }
            }
        });

        this.uiService.showElement('productSearchResults');
    }

    selectProductForEdit(product) {
        try {
            this.state.selectedProduct = product;
            this.state.isEditMode = true;

            this.uiService.hideElement('productSearchResults');
            this.getElement('productSearchInput').value = '';

            this.displaySelectedProduct(product);
            this.formService.fillForm(product);
            this.formService.setEditMode();

            this.variationService.updateLockedVariants(product);

            Object.keys(this.state.selectedItems).forEach(type => {
                this.updateSelectedItems(type);
            });
            this.cleanEditUrl();

        } catch (error) {
            console.error('Product selection failed:', error);
            this.uiService.showError('Ürün seçimi başarısız.');
        }
    }

    cleanEditUrl() {
        try {
            const url = new URL(window.location);
            if (url.searchParams.has('edit')) {
                url.searchParams.delete('edit');
                window.history.replaceState({}, '', url);
                console.log('🧹 Edit parameter cleaned from URL');
            }
        } catch (error) {
            console.error('❌ URL clean failed:', error);
        }
    }

    displaySelectedProduct(product) {
        const detailsElement = this.getElement('selectedProductDetails');
        if (detailsElement) {
            detailsElement.innerHTML = `
                <strong>${this.escapeHtml(product.name)}</strong> (${this.escapeHtml(product.productIdentifier)})<br>
                <small style="color: #6c757d;">${this.escapeHtml(product.description || 'Açıklama yok')}</small>
            `;
        }
        this.uiService.showElement('selectedProductInfo');
        this.getElement('editingProductId').value = product.id;
    }

    // Item Selection Methods
    async handleItemSearch(type) {
        const searchInput = this.getElement(`${type}Search`);
        if (!searchInput) return;

        const query = searchInput.value.trim();
        if (query.length < this.config.minSearchLength) {
            this.uiService.hideElement(`${type}Results`);
            return;
        }

        try {
            const results = await this.searchService.searchItems(type, query);
            this.displayItemSearchResults(type, results);
        } catch (error) {
            console.error(`${type} search failed:`, error);
        }
    }

    displayItemSearchResults(type, items) {
        const resultsDiv = this.getElement(`${type}Results`);
        if (!resultsDiv) return;

        if (items.length === 0) {
            resultsDiv.innerHTML = '<div style="padding: 1rem; text-align: center; color: #6c757d;">Sonuç bulunamadı</div>';
            this.uiService.showElement(`${type}Results`);
            return;
        }

        resultsDiv.innerHTML = items.map(item => `
            <div class="item-result" data-item-id="${item.id}" data-item-name="${this.escapeHtml(item.name)}" 
                    style="padding: 0.5rem; cursor: pointer; border-bottom: 1px solid #f1f3f4;">
                ${this.config.icons[type] || '📋'} ${this.escapeHtml(item.name)}
            </div>
        `).join('');

        resultsDiv.addEventListener('click', (e) => {
            const resultItem = e.target.closest('.item-result');
            if (resultItem) {
                const item = {
                    id: resultItem.dataset.itemId,
                    name: resultItem.dataset.itemName
                };
                this.addSelectedItem(type, item);
            }
        });

        this.uiService.showElement(`${type}Results`);
    }

    handleItemSelect(type, event) {
        const selectedOption = event.target.options[event.target.selectedIndex];
        if (selectedOption.value) {
            const item = {
                id: selectedOption.value,
                name: selectedOption.text
            };
            this.addSelectedItem(type, item);
            event.target.selectedIndex = 0;
        }
    }

    addSelectedItem(type, item) {
        try {
            const singleSelectTypes = ['categories'];

            if (singleSelectTypes.includes(type)) {
                this.state.selectedItems[type] = [item];
            } else {
                if (this.state.selectedItems[type].some(selectedItem => selectedItem.id === item.id)) {
                    return;
                }
                this.state.selectedItems[type].push(item);
            }

            this.updateSelectedItems(type);
            this.clearSearchInput(type);
        } catch (error) {
            console.error('Add selected item failed:', error);
        }
    }

    updateSelectedItems(type) {
        const selectedDiv = this.getElement(`${type}Selected`);
        const hiddenDiv = this.getElement(`${type}Hidden`);

        if (!selectedDiv || !hiddenDiv) return;

        if (this.state.selectedItems[type].length === 0) {
            selectedDiv.innerHTML = `<small style="color: #6c757d;">Seçilen ${this.config.typeLabels[type] || type} burada görünecek...</small>`;
            hiddenDiv.innerHTML = '';
            return;
        }

        selectedDiv.innerHTML = this.state.selectedItems[type].map(item => {
            const isLocked = this.isItemLocked(type, item);
            const lockIcon = isLocked ? '🔒 ' : '';
            const removeButton = isLocked ?
                `<span style="margin-left: 0.5rem; color: #6c757d; cursor: not-allowed;" title="Bu ${this.config.typeLabels[type]} varyantlarda kullanıldığı için kaldırılamaz">🔒</span>` :
                `<span onclick="productFormManager.removeSelectedItem('${type}', ${item.id})" style="margin-left: 0.5rem; cursor: pointer; font-weight: bold;">×</span>`;

            return `
                <span style="display: inline-block; background: ${isLocked ? '#6c757d' : '#007bff'}; color: white; padding: 0.25rem 0.5rem; margin: 0.25rem; border-radius: 12px; font-size: 0.875rem;">
                    ${lockIcon}${this.config.icons[type] || '📋'} ${this.escapeHtml(item.name)}
                    ${removeButton}
                </span>
            `;
        }).join('');

        hiddenDiv.innerHTML = this.state.selectedItems[type].map(item =>
            `<input type="hidden" name="${this.config.inputNames[type] || type + '[]'}" value="${item.id}">`
        ).join('');
    }

    isItemLocked(type, item) {
        if (type === 'colors' && this.state.lockedVariants.length > 0) {
            return this.state.lockedVariants.some(v => v.color === item.name);
        }
        return false;
    }

    removeSelectedItem(type, itemId) {
        const item = this.state.selectedItems[type].find(item => item.id == itemId);
        if (!item) return;

        if (this.isItemLocked(type, item)) {
            this.uiService.showError(`Bu ${this.config.typeLabels[type]} varyantlarda kullanıldığı için kaldırılamaz.`);
            return;
        }

        this.state.selectedItems[type] = this.state.selectedItems[type].filter(item => item.id != itemId);
        this.updateSelectedItems(type);
    }

    clearSearchInput(type) {
        const searchInput = this.getElement(`${type}Search`);
        if (searchInput) {
            searchInput.value = '';
        }
        this.uiService.hideElement(`${type}Results`);
    }

    // Image Upload Methods
    handleImageUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            this.uiService.showError('Lütfen geçerli bir resim dosyası seçin.');
            event.target.value = '';
            return;
        }

        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            this.uiService.showError('Resim dosyası çok büyük. Maksimum 5MB olmalıdır.');
            event.target.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = (e) => {
            try {
                const preview = this.getElement('imagePreview');
                if (preview) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
            } catch (error) {
                console.error('Image preview failed:', error);
                this.uiService.showError('Resim önizleme başarısız.');
            }
        };

        reader.onerror = () => {
            this.uiService.showError('Resim yüklenirken bir hata oluştu.');
            event.target.value = '';
        };

        reader.readAsDataURL(file);
    }

    checkForEditProduct() {
        try {
            const selectedProductData = document.getElementById('selectedProductData');
            if (selectedProductData) {
                const productData = JSON.parse(selectedProductData.textContent);
                if (productData && productData.id) {
                    console.log('🎯 Auto-loading product for edit:', productData.id);
                    this.simulateProductSearch(productData);
                    return;
                }
            }
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            if (editId) {
                console.log('🎯 Loading product via search for edit:', editId);
                this.performProductSearchById(editId);
            }
        } catch (error) {
            console.error('❌ Check edit product failed:', error);
            this.uiService.showNotification('Ürün yükleme hatası: ' + error.message, 'error');
        }
    }

    simulateProductSearch(productData) {
        try {
            console.log('🔍 Simulating search for product:', productData.name);
            const searchInput = this.getElement('productSearchInput');
            if (searchInput) {
                searchInput.value = productData.name;
            }
            const searchResults = [productData]; 
            this.displayProductSearchResults(searchResults);
            setTimeout(() => {
                this.selectProductForEdit(productData);
            }, 200);
        } catch (error) {
            console.error('❌ Simulate search failed:', error);
            throw error;
        }
    }

    async performProductSearchById(productId) {
        try {
            this.uiService.showLoading();
            const response = await fetch(`/product/search-products?q=${productId}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            if (data.items && data.items.length > 0) {
                const productData = data.items[0];
                console.log('✅ Product loaded via search:', productData);
                this.simulateProductSearch(productData);
            } else {
                throw new Error('Ürün bulunamadı');
            }
        } catch (error) {
            console.error('❌ Search by ID failed:', error);
            this.uiService.showNotification('Ürün yüklenemedi: ' + error.message, 'error');
        } finally {
            this.uiService.hideLoading();
        }
    }

    // Color Management Methods
    async handleAddNewColor() {
        const colorInput = this.getElement('newColorInput');
        if (!colorInput) return;

        const newColor = colorInput.value.trim();
        if (!newColor) {
            this.uiService.showError('Lütfen bir renk girin.');
            return;
        }

        try {
            const result = await this.searchService.addColor(newColor);
            this.addSelectedItem('colors', { id: result.id, name: newColor });
            colorInput.value = '';
            this.uiService.showSuccess('Renk başarıyla eklendi.');
        } catch (error) {
            console.error('Add color failed:', error);
            this.uiService.showError('Renk eklenemedi.');
        }
    }

    // Navigation Methods
    handleGoToVariations() {
        if (!this.validationService.validateStep1()) {
            return;
        }

        try {
            this.variationService.generateMatrix();
            this.showStep(2);
        } catch (error) {
            console.error('Generate variations failed:', error);
            this.uiService.showError('Varyasyon matrisi oluşturulamadı.');
        }
    }

    showStep(stepNumber) {
        this.state.currentStep = stepNumber;

        for (let i = 1; i <= 2; i++) {
            const stepElement = this.getElement(`step${i}`);
            if (stepElement) {
                stepElement.style.display = i === stepNumber ? 'block' : 'none';
            }
        }
    }

    // Variant Management Methods
    async handleDeleteVariant(button) {
        const color = button.dataset.color;
        const size = button.dataset.size;
        const custom = button.dataset.custom || null;

        const confirmMessage = `"${color} - ${size}${custom ? ' - ' + custom : ''}" varyantını silmek istediğinizden emin misiniz?\n\nBu işlem ürünü yayından kaldıracaktır (unpublish).`;

        if (!confirm(confirmMessage)) {
            return;
        }

        try {
            this.uiService.showLoading();

            const response = await fetch(this.config.endpoints.deleteVariant, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    productId: this.state.selectedProduct.id,
                    variantData: {
                        color: color,
                        size: size,
                        custom: custom
                    }
                })
            });

            if (!response.ok) {
                throw new Error('Variant silme başarısız');
            }

            const result = await response.json();

            if (result.success) {
                this.uiService.showSuccess('Varyant silindi.');
                this.removeVariantFromLocked(color, size, custom);
                this.variationService.generateMatrix();
            } else {
                throw new Error(result.message || 'Variant silme başarısız');
            }

        } catch (error) {
            console.error('Delete variant failed:', error);
            this.uiService.showError(`Variant silinemedi: ${error.message}`);
        } finally {
            this.uiService.hideLoading();
        }
    }

    removeVariantFromLocked(color, size, custom) {
        const normalizedCustom = (custom === undefined || custom === null || custom === '') ? null : custom;

        this.state.lockedVariants = this.state.lockedVariants.filter(v => {
            const normalizedLockedCustom = (v.custom === undefined || v.custom === null) ? null : v.custom;

            const isMatch = (
                v.color === color &&
                v.size === size &&
                normalizedLockedCustom === normalizedCustom
            );

            return !isMatch;
        });
    }

    // Form Submission Methods
    async handleFormSubmit() {
        try {
            if (!this.validationService.validateStep1()) {
                return;
            }

            this.uiService.showLoading();
            this.variationService.collectVariationsData();

            await this.submitFormWithFormData();

        } catch (error) {
            console.error('Form submit failed:', error);
            this.uiService.showError('Form gönderilirken bir hata oluştu.');
        } finally {
            this.uiService.hideLoading();
        }
    }

    async submitFormWithFormData() {
        try {
            console.log('🚀 Starting FormData submission');
            
            const form = this.getElement('productForm');
            if (!form) {
                throw new Error('Form element not found');
            }
            
            const formData = new FormData();
            
            const imageInput = this.getElement('imageInput');
            console.log('🔍 Image input check:', {
                element: !!imageInput,
                files: imageInput ? imageInput.files : null,
                filesLength: imageInput && imageInput.files ? imageInput.files.length : 0,
                hasFile: !!(imageInput && imageInput.files && imageInput.files.length > 0)
            });
            
            if (imageInput && imageInput.files && imageInput.files.length > 0) {
                const file = imageInput.files[0];
                console.log('🔍 File details:', {
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    lastModified: file.lastModified
                });
                
                formData.append('productImage', file, file.name);
                console.log('✅ File appended to FormData');
                
                const hasImageInFormData = formData.has('productImage');
                const imageFromFormData = formData.get('productImage');
                console.log('🔍 FormData verification:', {
                    hasImage: hasImageInFormData,
                    retrievedFile: imageFromFormData,
                    isFileInstance: imageFromFormData instanceof File
                });
                
            } else {
                console.log('⚠️ No image file found');
                if (!this.state.isEditMode) {
                    console.log('❌ Not in edit mode, image required');
                    throw new Error('Lütfen bir resim seçin.');
                }
            }
            
            const textFields = [
                { id: 'productName', name: 'productName' },
                { id: 'productIdentifier', name: 'productIdentifier' }, 
                { id: 'productDescription', name: 'productDescription' }
            ];
            
            textFields.forEach(({ id, name }) => {
                const field = this.getElement(id);
                if (field) {
                    formData.append(name, field.value || '');
                    console.log(`✅ Added ${name}:`, field.value || '(empty)');
                }
            });
            
            const hiddenFields = [
                { id: 'editingProductId', name: 'editingProductId' },
                { id: 'sizeTableData', name: 'sizeTableData' },
                { id: 'customTableData', name: 'customTableData' },
                { id: 'variationsData', name: 'variationsData' }
            ];
            
            hiddenFields.forEach(({ id, name }) => {
                const field = this.getElement(id);
                if (field) {
                    formData.append(name, field.value || '');
                    console.log(`✅ Added ${name}:`, (field.value || '').substring(0, 50) + '...');
                }
            });

            const hiddenInputs = form.querySelectorAll('input[type="hidden"][name$="[]"], input[type="hidden"][name="productCategory"]');
            console.log('🔍 Found hidden inputs:', hiddenInputs.length);
            
            hiddenInputs.forEach(input => {
                if (input.name && input.value) {
                    formData.append(input.name, input.value);
                    console.log(`✅ Added ${input.name}:`, input.value);
                }
            });
            
            console.log('📋 Final FormData check:');
            let totalEntries = 0;
            let hasImageFile = false;
            
            for (let [key, value] of formData.entries()) {
                totalEntries++;
                if (key === 'productImage') {
                    hasImageFile = true;
                    console.log(`  ${key}: [File] ${value.name} (${value.size} bytes, ${value.type})`);
                } else {
                    console.log(`  ${key}: ${value}`);
                }
            }
            
            console.log(`📊 FormData summary: ${totalEntries} entries, hasImage: ${hasImageFile}`);
            
            if (!hasImageFile && !this.state.isEditMode) {
                throw new Error('Resim dosyası FormData\'ya eklenemedi!');
            }
            
            console.log('📡 Sending request to:', form.action);
            
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('📡 Response received:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok
            });
            
            console.log('📄 Response headers:');
            for (let [key, value] of response.headers.entries()) {
                console.log(`  ${key}: ${value}`);
            }
            
            if (response.ok) {
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    console.log('📄 JSON Response:', result);
                    
                    if (result.success) {
                        this.uiService.showSuccess(result.message);
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        throw new Error(result.message || 'Ürün kaydedilemedi.');
                    }
                } else {
                    this.uiService.showSuccess('Ürün başarıyla kaydedildi!');
                    setTimeout(() => window.location.reload(), 1500);
                }
            } else {
                const errorText = await response.text();
                console.error('❌ Server Error Response:', errorText.substring(0, 1000));
                throw new Error(`Server error: ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Submit failed:', error);
            throw error;
        }
    }
    
    clearForm() {
        try {
            this.formService.clearForm();
            this.showStep(1);
        } catch (error) {
            console.error('Clear form failed:', error);
            this.uiService.showError('Form temizlenirken bir hata oluştu.');
        }
    }

    // Utility Methods
    getElement(id) {
        const element = document.getElementById(id);
        if (!element) {
            console.warn(`Element not found: ${id}`);
        }
        return element;
    }

    escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    debounce(func, wait) {
        return (...args) => {
            clearTimeout(this.state.searchTimeout);
            this.state.searchTimeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    validateEnvironment() {
        const requiredElements = ['productForm', 'step1', 'step2'];
        const missingElements = requiredElements.filter(id => !document.getElementById(id));

        if (missingElements.length > 0) {
            console.error('Missing required elements:', missingElements);
            this.uiService.showError('Uygulama düzgün yüklenemedi. Sayfayı yenileyin.');
            return false;
        }

        return true;
    }
}

/**
 * Arama Servisi
 */
class SearchService {
    constructor(config) {
        this.config = config;
    }

    async searchProducts(query) {
        const response = await fetch(`${this.config.endpoints.productSearch}?q=${encodeURIComponent(query)}`);
        if (!response.ok) throw new Error('Product search failed');
        const data = await response.json();
        return data.items || [];
    }

    async searchItems(type, query) {
        const response = await fetch(`${this.config.endpoints.itemSearch}/${type}?q=${encodeURIComponent(query)}`);
        if (!response.ok) throw new Error(`${type} search failed`);
        const data = await response.json();
        return data.items || [];
    }

    async addColor(name) {
        const response = await fetch(this.config.endpoints.addColor, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfToken
            },
            body: JSON.stringify({ name })
        });

        if (!response.ok) throw new Error('Add color failed');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Add color failed');
        }

        return data;
    }
}

/**
 * Form Servisi
 */
class FormService {
    constructor(config, state) {
        this.config = config;
        this.state = state;
    }

    fillForm(product) {
        try {
            this.resetState();
            this.setFieldValues(product);
            this.setupSelectedItems(product);
            this.setProductImage(product);
            this.fillTables(product);
        } catch (error) {
            console.error('Fill form failed:', error);
            throw error;
        }
    }

    resetState() {
        this.state.lockedVariants = [];
        this.state.selectedItems = {
            colors: [],
            brands: [],
            marketplaces: [],
            categories: []
        };
    }

    setFieldValues(product) {
        this.setFieldValue('productName', product.name);
        this.setFieldValue('productIdentifier', product.productIdentifier);
        this.setFieldValue('productDescription', product.description);
    }

    setFieldValue(fieldId, value) {
        const field = document.getElementById(fieldId);
        if (field && value !== undefined && value !== null) {
            field.value = value;
        }
    }

    setupSelectedItems(product) {
        if (product.categoryId) {
            this.state.selectedItems.categories = [{
                id: product.categoryId,
                name: product.categoryName
            }];
        }

        ['brands', 'marketplaces', 'variantColors'].forEach(key => {
            if (product[key] && Array.isArray(product[key])) {
                const targetKey = key === 'variantColors' ? 'colors' : key;
                this.state.selectedItems[targetKey] = product[key];
            }
        });
    }

    setProductImage(product) {
        if (product.imagePath) {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.innerHTML = `<img src="${product.imagePath}" alt="Product Image" style="width: 100%; height: 100%; object-fit: cover;">`;
            }
        }
    }

    fillTables(product) {
        if (product.sizeTable && Array.isArray(product.sizeTable)) {
            const processedUsedSizes = this.processUsedItems(product.usedSizes);
            window.productFormManager.tableService.populateSizeTable(
                product.sizeTable,
                processedUsedSizes
            );
        }

        if (product.customTable && product.customTable.rows) {
            const processedUsedCustoms = this.processUsedItems(product.usedCustoms);
            window.productFormManager.tableService.populateCustomTable(
                product.customTable,
                processedUsedCustoms
            );
        }
    }

    processUsedItems(usedItems) {
        if (Array.isArray(usedItems)) {
            return usedItems;
        } else if (typeof usedItems === 'object' && usedItems !== null) {
            return Object.values(usedItems);
        }
        return [];
    }

    setEditMode() {
        const identifierField = document.getElementById('productIdentifier');
        if (identifierField) {
            identifierField.readOnly = true;
            identifierField.style.backgroundColor = '#e9ecef';
            identifierField.style.cursor = 'not-allowed';
        }

        const imageInput = document.getElementById('imageInput');
        if (imageInput) {
            imageInput.removeAttribute('required');
        }

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.textContent = '💾 Ürünü Güncelle';
        }
    }

    clearForm() {
        this.resetState();
        this.state.selectedProduct = null;
        this.state.isEditMode = false;

        const form = document.getElementById('productForm');
        if (form) {
            form.reset();
        }

        this.clearUIElements();
        this.clearEditMode();
    }

    clearUIElements() {
        const elementsToHide = ['selectedProductInfo', 'sizeTablePreview', 'customTablePreview'];
        elementsToHide.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.style.display = 'none';
            }
        });

        const hiddenInputs = ['editingProductId', 'sizeTableData', 'customTableData'];
        hiddenInputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.value = '';
            }
        });

        const imagePreview = document.getElementById('imagePreview');
        if (imagePreview) {
            imagePreview.innerHTML = '📷';
        }

        const tableBodies = ['#sizeTable tbody', '#customTable tbody'];
        tableBodies.forEach(selector => {
            const tbody = document.querySelector(selector);
            if (tbody) {
                tbody.innerHTML = '';
            }
        });
    }

    clearEditMode() {
        const identifierField = document.getElementById('productIdentifier');
        if (identifierField) {
            identifierField.readOnly = false;
            identifierField.style.backgroundColor = '';
            identifierField.style.cursor = '';
        }

        const imageInput = document.getElementById('imageInput');
        if (imageInput) {
            imageInput.setAttribute('required', 'required');
        }

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.textContent = 'Kaydet';
        }
    }
}

/**
 * Tablo Servisi
 */
class TableService {
    addSizeRow(values = {}, isLocked = false) {
        const tbody = document.querySelector('#sizeTable tbody');
        if (!tbody) return;

        const tr = document.createElement('tr');
        if (isLocked) {
            tr.classList.add('locked-row');
        }

        tr.innerHTML = `
            <td data-label="Beden">
                <input type="text" class="form-control" value="${values.beden || ''}" ${isLocked ? 'readonly' : ''} required>
                ${isLocked ? '<small class="text-muted">🔒 Varyantlarda kullanılıyor</small>' : ''}
            </td>
            <td data-label="En"><input type="number" class="form-control" value="${values.en || ''}" step="any" ${isLocked ? 'readonly' : ''} required></td>
            <td data-label="Boy"><input type="number" class="form-control" value="${values.boy || ''}" step="any" ${isLocked ? 'readonly' : ''} required></td>
            <td data-label="Yükseklik"><input type="number" class="form-control" value="${values.yukseklik || ''}" step="any" ${isLocked ? 'readonly' : ''} required></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row-btn w-100" ${isLocked ? 'style="opacity: 0.5; cursor: not-allowed;" title="Bu beden varyantlarda kullanıldığı için silinemez"' : ''}>
                    ${isLocked ? '🔒 Sil' : 'Sil'}
                </button>
            </td>
        `;

        tbody.appendChild(tr);
    }

    addCustomRow(value = {}, isLocked = false) {
        const tbody = document.querySelector('#customTable tbody');
        if (!tbody) return;

        const tr = document.createElement('tr');
        if (isLocked) {
            tr.classList.add('locked-row');
        }

        const degerValue = typeof value === 'object' ? (value.deger || '') : value;

        tr.innerHTML = `
            <td data-label="Değer">
                <input type="text" class="form-control" value="${degerValue}" ${isLocked ? 'readonly' : ''} required>
                ${isLocked ? '<small class="text-muted">🔒 Varyantlarda kullanılıyor</small>' : ''}
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row-btn" ${isLocked ? 'style="opacity: 0.5; cursor: not-allowed;" title="Bu değer varyantlarda kullanıldığı için silinemez"' : ''}>
                    ${isLocked ? '🔒 Sil' : 'Sil'}
                </button>
            </td>
        `;

        tbody.appendChild(tr);
    }

    saveSizeTable() {
        const rows = this.collectTableData('#sizeTable tbody tr', ['beden', 'en', 'boy', 'yukseklik']);
        if (!this.validateTableData(rows)) {
            window.productFormManager.uiService.showError('Lütfen tablodaki tüm alanları doldurun.');
            return;
        }

        document.getElementById('sizeTableData').value = JSON.stringify(rows);
        this.updateSizeTablePreview(rows);
        this.closeModal('sizeTableModal');
    }

    saveCustomTable() {
        const titleInput = document.getElementById('customTableTitle');
        const title = titleInput ? titleInput.value.trim() : '';

        if (!title) {
            window.productFormManager.uiService.showError('Lütfen Custom Tablo için bir başlık girin.');
            return;
        }

        const rows = this.collectTableData('#customTable tbody tr', ['deger']);
        if (!this.validateTableData(rows)) {
            window.productFormManager.uiService.showError('Lütfen tablodaki tüm değer alanlarını doldurun.');
            return;
        }

        const tableData = { title, rows };
        document.getElementById('customTableData').value = JSON.stringify(tableData);
        this.updateCustomTablePreview(tableData);
        this.closeModal('customTableModal');
    }

    collectTableData(selector, fields) {
        const rows = [];
        document.querySelectorAll(selector).forEach(tr => {
            const rowData = {};
            fields.forEach((field, index) => {
                const input = tr.children[index]?.querySelector('input');
                rowData[field] = input ? input.value.trim() : '';
            });
            rows.push(rowData);
        });
        return rows;
    }

    validateTableData(rows) {
        return rows.every(row => Object.values(row).every(value => value !== ''));
    }

    updateSizeTablePreview(rows) {
        const previewContainer = document.getElementById('sizeTablePreview');
        if (!previewContainer) return;

        if (rows.length > 0) {
            let html = '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Beden</th><th>En</th><th>Boy</th><th>Yükseklik</th></tr></thead><tbody>';
            rows.forEach(row => {
                html += `<tr><td>${row.beden}</td><td>${row.en}</td><td>${row.boy}</td><td>${row.yukseklik}</td></tr>`;
            });
            html += '</tbody></table></div>';
            previewContainer.innerHTML = html;
            previewContainer.style.display = 'block';
        } else {
            previewContainer.style.display = 'none';
        }
    }

    updateCustomTablePreview(tableData) {
        const previewContainer = document.getElementById('customTablePreview');
        if (!previewContainer) return;

        if (tableData.rows && tableData.rows.length > 0) {
            let html = `<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>${tableData.title || 'Custom'}</th></tr></thead><tbody>`;
            tableData.rows.forEach(row => {
                html += `<tr><td>${row.deger}</td></tr>`;
            });
            html += '</tbody></table></div>';
            previewContainer.innerHTML = html;
            previewContainer.style.display = 'block';
        } else {
            previewContainer.style.display = 'none';
        }
    }

    populateSizeTable(sizeTable, usedSizes = []) {
        const tbody = document.querySelector('#sizeTable tbody');
        if (tbody) {
            tbody.innerHTML = '';
        }

        sizeTable.forEach(row => {
            const sizeValue = row.beden || '';
            const isLocked = usedSizes.some(usedSize => {
                if (typeof usedSize === 'string') {
                    const extractedSize = usedSize.includes(' (En:') ?
                        usedSize.split(' (En:')[0] :
                        usedSize.split(' (')[0];
                    return extractedSize === sizeValue;
                }
                return usedSize === sizeValue;
            });

            this.addSizeRow(row, isLocked);
        });

        document.getElementById('sizeTableData').value = JSON.stringify(sizeTable);
        this.updateSizeTablePreview(sizeTable);
    }

    populateCustomTable(customTable, usedCustoms = []) {
        const customTitleElement = document.getElementById('customTableTitle');
        if (customTitleElement) {
            customTitleElement.value = customTable.title || '';
        }

        const tbody = document.querySelector('#customTable tbody');
        if (tbody) {
            tbody.innerHTML = '';
        }

        if (customTable.rows && customTable.rows.length > 0) {
            customTable.rows.forEach(row => {
                const customValue = row.deger || '';
                const isLocked = usedCustoms.includes(customValue);
                this.addCustomRow(row, isLocked);
            });
        }

        document.getElementById('customTableData').value = JSON.stringify(customTable);
        this.updateCustomTablePreview(customTable);
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal && window.bootstrap) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    }
}

/**
 * Varyasyon Servisi
 */
class VariationService {
    constructor(state) {
        this.state = state;
    }

    updateLockedVariants(product) {
        if (!product || !product.variants || product.variants.length === 0) {
            this.state.lockedVariants = [];
            return;
        }
        this.state.lockedVariants = product.variants.map(v => {
            let sizeValue = v.size || v.sizeName || v.beden || v.productVariantSize?.name || v.productVariantSize?.beden;
            let colorValue = v.color || v.colorName || v.renk || v.productVariantColor?.name;
            let customValue = v.custom || v.customName || v.customValue || v.productVariantCustom?.deger;
            let published = typeof v.published !== 'undefined' ? v.published : true;
            if (sizeValue && typeof sizeValue === 'string') {
                if (sizeValue.includes(' (En:')) {
                    sizeValue = sizeValue.split(' (En:')[0];
                } else if (sizeValue.includes(' (')) {
                    sizeValue = sizeValue.split(' (')[0];
                }
            }
            if (customValue === undefined || customValue === '' || customValue === 'undefined') {
                customValue = null;
            }
            return {
                color: colorValue,
                size: sizeValue,
                custom: customValue,
                published: published, 
                id: v.id
            };
        });
        
        console.log('Locked variants updated:', this.state.lockedVariants);
    }

    generateMatrix() {
        try {
            const colors = this.state.selectedItems.colors.map(c => c.name);
            const sizesData = this.getSizesData();
            const sizes = sizesData.map(s => s.beden);
            const customs = this.getCustomsData();

            if (colors.length === 0 || sizes.length === 0) {
                this.showMatrixError('Varyasyon matrisini oluşturmak için lütfen en az bir Renk ve bir Beden seçin.');
                return;
            }

            const combos = this.generateCombinations(colors, sizes, customs);
            const uniqueCombos = this.filterUniqueCombinations(combos);
            const html = this.generateMatrixHTML(uniqueCombos, sizesData, customs);

            const container = document.getElementById('variationTableContainer');
            if (container) {
                container.innerHTML = html;
            }

        } catch (error) {
            console.error('Generate matrix failed:', error);
            this.showMatrixError('Varyasyon matrisi oluşturulurken bir hata oluştu.');
        }
    }

    filterUniqueCombinations(combos) {
        const seen = new Set();
        const uniqueCombos = [];

        combos.forEach(combo => {
            const [color, size, custom] = combo;
            const normalizedCustom = (custom === undefined || custom === null || custom === '') ? null : custom;
            const key = `${color}-${size}-${normalizedCustom || 'null'}`;
            
            if (!seen.has(key)) {
                seen.add(key);
                uniqueCombos.push([color, size, normalizedCustom]);
            }
        });

        return uniqueCombos;
    }

    getSizesData() {
        try {
            return JSON.parse(document.getElementById('sizeTableData').value || '[]');
        } catch (error) {
            console.error('Parse sizes data failed:', error);
            return [];
        }
    }

    getCustomsData() {
        try {
            const customData = JSON.parse(document.getElementById('customTableData').value || '{}');
            if (customData.rows && customData.rows.length > 0) {
                return customData.rows
                    .map(row => row.deger)
                    .filter(value => value && value.trim() !== '');
            }
        } catch (error) {
            console.error('Parse customs data failed:', error);
        }
        return [];
    }

    generateCombinations(colors, sizes, customs) {
        const cartesianProduct = (arrays) => {
            const filteredArrays = arrays.filter(arr => arr && arr.length > 0);
            if (filteredArrays.length === 0) return [];
            return filteredArrays.reduce((a, b) => a.flatMap(d => b.map(e => [...(Array.isArray(d) ? d : [d]), e])));
        };

        if (customs.length > 0) {
            return cartesianProduct([colors, sizes, customs]);
        } else {
            return cartesianProduct([colors, sizes]).map(item => [...item, null]);
        }
    }

    generateMatrixHTML(combos, sizesData, customs) {
        const hasCustomData = customs.length > 0;

        let html = '<table class="table table-bordered"><thead><tr>';
        html += '<th>Seç</th><th>Renk</th><th>Beden</th>';
        if (hasCustomData) html += '<th>Custom</th>';
        html += '<th>İşlem</th>';
        html += '</tr></thead><tbody>';

        combos.forEach((combo, i) => {
            const [color, size, custom] = combo;
            const isLocked = this.isVariantLocked(color, size, custom);
            const sizeLabel = this.formatSizeLabel(size, sizesData);

            const rowClass = isLocked ? 'variant-locked' : '';
            const lockIcon = isLocked ? '🔒 ' : '';
            const checkboxAttributes = isLocked ? 'disabled checked' : '';

            html += `<tr class="${rowClass}">
                <td>
                    <input type="checkbox" class="variation-checkbox" data-index="${i}" ${checkboxAttributes}>
                    ${isLocked ? '<br><small>Mevcut varyant</small>' : ''}
                </td>
                <td>${lockIcon}${color}</td>
                <td>${lockIcon}${sizeLabel}</td>`;

            if (hasCustomData) {
                html += `<td>${lockIcon}${custom || ''}</td>`;
            }

            html += `<td>`;
            if (isLocked) {
                html += `<button type="button" class="btn btn-danger btn-sm delete-variant-btn" 
                            data-color="${color}" data-size="${size}" data-custom="${custom || ''}"
                            title="Bu varyantı sil ve ürünü unpublish yap">
                            🗑️ Sil
                        </button>`;
            } else {
                html += '<span class="text-muted">-</span>';
            }
            html += `</td></tr>`;
        });

        html += '</tbody></table>';
        return html;
    }

    isVariantLocked(color, size, custom) {
        return this.state.lockedVariants.some(v => {
            if (v.published === false) {
                return false;
            }
            
            const colorMatch = (v.color === color);
            const sizeMatch = (v.size === size);
            const normalizedLockedCustom = (v.custom === undefined || v.custom === null) ? null : v.custom;
            const normalizedCheckingCustom = (custom === undefined || custom === null) ? null : custom;
            const customMatch = (normalizedLockedCustom === normalizedCheckingCustom);

            return colorMatch && sizeMatch && customMatch;
        });
    }

    formatSizeLabel(size, sizesData) {
        const sizeObj = sizesData.find(s => s.beden === size);
        if (sizeObj) {
            return `${size} (En: ${sizeObj.en}, Boy: ${sizeObj.boy}, Yükseklik: ${sizeObj.yukseklik})`;
        }
        return size;
    }

    showMatrixError(message) {
        const container = document.getElementById('variationTableContainer');
        if (container) {
            container.innerHTML = `<div class="alert alert-warning">${message}</div>`;
        }
    }

    collectVariationsData() {
        const rows = [];
        const customData = this.getCustomsData();
        const hasCustomData = customData.length > 0;

        document.querySelectorAll('#variationTableContainer tbody tr').forEach(tr => {
            const checkbox = tr.querySelector('.variation-checkbox');
            if (checkbox && checkbox.checked && !checkbox.disabled) {
                const tds = tr.querySelectorAll('td');
                const renk = tds[1].innerText.replace('🔒 ', '');
                const beden = tds[2].innerText.replace('🔒 ', '').split(' (')[0];

                const rowData = { renk, beden };

                if (hasCustomData && tds.length > 3) {
                    const customValue = tds[3].innerText.replace('🔒 ', '').trim();
                    if (customValue && customValue !== '') {
                        rowData.custom = customValue;
                    }
                }

                rows.push(rowData);
            }
        });

        const variationsInput = document.getElementById('variationsData');
        if (variationsInput) {
            variationsInput.value = JSON.stringify(rows);
        }
    }
}

/**
 * Doğrulama Servisi
 */
class ValidationService {
    validateStep1() {
        const step1 = document.getElementById('step1');
        if (!step1) return false;

        let isValid = true;

        // Required fields validation
        const requiredInputs = step1.querySelectorAll('input[required], textarea[required], select[required]');
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        // Image validation
        const imageInput = document.getElementById('imageInput');
        const isEditMode = window.productFormManager && window.productFormManager.state.isEditMode;

        if (imageInput && !isEditMode) {
            if (!imageInput.files || imageInput.files.length === 0) {
                this.showFieldError(imageInput, 'Lütfen bir resim seçin.');
                isValid = false;
            } else {
                const file = imageInput.files[0];
                if (!file.type.startsWith('image/')) {
                    this.showFieldError(imageInput, 'Lütfen geçerli bir resim dosyası seçin.');
                    isValid = false;
                } else if (file.size > 5 * 1024 * 1024) {
                    this.showFieldError(imageInput, 'Resim dosyası çok büyük (max 5MB).');
                    isValid = false;
                } else {
                    this.clearFieldError(imageInput);
                }
            }
        }

        // Selected items validation
        const selectedItems = window.productFormManager?.state?.selectedItems || {};

        if (!selectedItems.colors || selectedItems.colors.length === 0) {
            this.showContainerError('colorsSelected', 'En az bir renk seçmelisiniz.');
            isValid = false;
        } else {
            this.clearContainerError('colorsSelected');
        }

        const sizeTableData = document.getElementById('sizeTableData')?.value;
        if (!sizeTableData || sizeTableData === '[]') {
            this.showContainerError('sizeTableBtn', 'Beden tablosu oluşturmalısınız.');
            isValid = false;
        } else {
            this.clearContainerError('sizeTableBtn');
        }

        if (!isValid) {
            if (window.productFormManager) {
                window.productFormManager.uiService.showError('Lütfen tüm zorunlu alanları doldurun.');
            }
        }

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('is-invalid');
        field.title = message;
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.title = '';
    }

    showContainerError(containerId, message) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.borderColor = '#dc3545';
            container.title = message;
        }
    }

    clearContainerError(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.borderColor = '#dee2e6';
            container.title = '';
        }
    }
}

/**
 * UI Servisi
 */
class UIService {
    showElement(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = 'block';
        }
    }

    hideElement(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = 'none';
        }
    }

    showLoading() {
        this.showElement('loadingOverlay');
        const form = document.getElementById('productForm');
        if (form) {
            form.classList.add('form-disabled');
        }
    }

    hideLoading() {
        this.hideElement('loadingOverlay');
        const form = document.getElementById('productForm');
        if (form) {
            form.classList.remove('form-disabled');
        }
    }

    showError(message) {
        alert(`❌ Hata: ${message}`);
    }

    showSuccess(message) {
        alert(`✅ Başarılı: ${message}`);
    }
}

/**
 * Uygulama Başlatma
 */
document.addEventListener('DOMContentLoaded', function() {
    try {
        window.productFormManager = new ProductFormManager();
        console.log('✅ Product Form Manager initialized successfully');
    } catch (error) {
        console.error('❌ Product Form Manager initialization failed:', error);
        alert('Uygulama başlatılamadı. Lütfen sayfayı yenileyin.');
    }
});
