<script>
    let currentPage = 1;
    let dataEntries = [];
    let isSubmitting = false;
    let sessionId = null;
    let draftData = @json($draft ?? null);

    const ratingItems = [
        { id: 'informasi_pelayanan', title: 'Informasi pelayanan pada unit layanan ini tersedia melalui media elektronik maupun non elektronik.' },
        { id: 'persyaratan', title: 'Persyaratan pelayanan yang ditetapkan mudah dipenuhi/disiapkan oleh konsumen.' },
        { id: 'prosedur', title: 'Prosedur/alur pelayanan yang ditetapkan mudah diikuti/dilakukan.' },
        { id: 'jangka_waktu', title: 'Jangka waktu penyelesaian pelayanan yang diterima sesuai dengan yang ditetapkan.' },
        { id: 'biaya', title: 'Biaya pelayanan yang dibayarkan sesuai dengan biaya yang ditetapkan.' },
        { id: 'produk', title: 'Produk pelayanan yang diterima sesuai dengan yang dijanjikan.' },
        { id: 'sarana', title: 'Sarana dan prasarana pendukung pelayanan memberikan kenyamanan.' },
        { id: 'akses_data', title: 'Data BPS mudah diakses.' },
        { id: 'respons_petugas', title: 'Petugas pelayanan dan/atau aplikasi pelayanan online merespon dengan baik.' },
        { id: 'informasi_petugas', title: 'Petugas pelayanan dan/atau aplikasi pelayanan online mampu memberikan informasi yang jelas.' },
        { id: 'fasilitas_pengaduan', title: 'Fasilitas pengaduan PST mudah diakses.' },
        { id: 'diskriminasi', title: 'Tidak ada diskriminasi dalam pelayanan.' },
        { id: 'kecurangan', title: 'Tidak ada pelayanan di luar prosedur/kecurangan pelayanan.' },
        { id: 'gratifikasi', title: 'Tidak ada penerimaan gratifikasi.' },
        { id: 'pungli', title: 'Tidak ada pungutan liar (pungli) dalam pelayanan.' },
        { id: 'percaloan', title: 'Tidak ada praktik percaloan dalam pelayanan.' }
    ];

    $(document).ready(function() {

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Load draft if exists
        if (draftData) {
            loadDraftData(draftData);
        }

        initializeForm();
    });

    // Load draft data to form - FIXED VERSION
    function loadDraftData(draft) {
        sessionId = draft.session_id;
        currentPage = draft.last_page || 1;

        // Load basic data
        $('#nama').val(draft.nama || '');
        $('#email').val(draft.email || '');
        $('#nomor_hp').val(draft.nomor_hp || '');
        
        // Jenis Kelamin
        if (draft.jenis_kelamin) {
            $(`input[name="jenis_kelamin"][value="${draft.jenis_kelamin}"]`).prop('checked', true).trigger('change');
        }
        
        $('#kelompok_umur').val(draft.kelompok_umur || '');
        $('#pendidikan').val(draft.pendidikan || '');
        
        // Pekerjaan
        $('#pekerjaan').val(draft.pekerjaan || '').trigger('change');
        if (draft.pekerjaan === 'Lainnya' && draft.pekerjaan_lainnya) {
            $('#pekerjaan_lainnya').val(draft.pekerjaan_lainnya);
        }
        
        // Kategori Instansi
        $('#kategori_instansi').val(draft.kategori_instansi || '').trigger('change');
        if (draft.kategori_instansi === 'Lainnya' && draft.kategori_instansi_lainnya) {
            $('#kategori_instansi_lainnya').val(draft.kategori_instansi_lainnya);
        }
        
        $('#nama_instansi').val(draft.nama_instansi || '');
        
        // Penyandang Disabilitas
        if (draft.penyandang_disabilitas) {
            $(`input[name="penyandang_disabilitas"][value="${draft.penyandang_disabilitas}"]`).prop('checked', true);
            toggleDisabilityFields();
            
            // Load Jenis Disabilitas (checkbox array)
            if (draft.jenis_disabilitas) {
                const jenisDisabilitasArray = typeof draft.jenis_disabilitas === 'string' 
                    ? draft.jenis_disabilitas.split(', ') 
                    : draft.jenis_disabilitas;
                
                jenisDisabilitasArray.forEach(jenis => {
                    $(`input[name="jenis_disabilitas[]"][value="${jenis}"]`).prop('checked', true);
                });
            }
        }

        // Load Tujuan Penggunaan Data (checkbox array)
        if (draft.tujuan_penggunaan) {
            const tujuanArray = typeof draft.tujuan_penggunaan === 'string' 
                ? draft.tujuan_penggunaan.split(', ') 
                : draft.tujuan_penggunaan;
            
            tujuanArray.forEach(tujuan => {
                $(`input[name="tujuan[]"][value="${tujuan}"]`).prop('checked', true);
            });
            
            // Check if "Lainnya" is selected and load the text
            if (tujuanArray.includes('Lainnya') && draft.tujuan_lainnya) {
                $('#tujuan_lainnya').removeClass('hidden').val(draft.tujuan_lainnya);
            }
        }

        // Load Jenis Layanan (checkbox array)
        if (draft.jenis_layanan) {
            const layananArray = typeof draft.jenis_layanan === 'string' 
                ? draft.jenis_layanan.split(', ') 
                : draft.jenis_layanan;
            
            layananArray.forEach(layanan => {
                $(`input[name="layanan[]"][value="${layanan}"]`).prop('checked', true);
            });
        }

        // Load Sarana Layanan (checkbox array)
        if (draft.sarana_layanan) {
            const saranaArray = typeof draft.sarana_layanan === 'string' 
                ? draft.sarana_layanan.split(', ') 
                : draft.sarana_layanan;
            
            saranaArray.forEach(sarana => {
                $(`input[name="sarana[]"][value="${sarana}"]`).prop('checked', true);
            });
            
            // Check if "Lainnya" is selected and load the text
            if (saranaArray.includes('Lainnya') && draft.sarana_lainnya) {
                $('#sarana_lainnya').removeClass('hidden').val(draft.sarana_lainnya);
            }
        }

        // Load ratings - WAIT for ratings to be rendered first
        setTimeout(() => {
            ratingItems.forEach(item => {
                if (draft[item.id + '_kepentingan']) {
                    const kepentinganVal = parseInt(draft[item.id + '_kepentingan']);
                    $(`#${item.id}_kepentingan`).val(kepentinganVal);
                    updateStarDisplay(item.id + '_kepentingan', kepentinganVal);
                }
                if (draft[item.id + '_kepuasan']) {
                    const kepuasanVal = parseInt(draft[item.id + '_kepuasan']);
                    $(`#${item.id}_kepuasan`).val(kepuasanVal);
                    updateStarDisplay(item.id + '_kepuasan', kepuasanVal);
                }
            });
        }, 300);

        // Load data entries
        if (draft.data_entries && draft.data_entries.length > 0) {
            dataEntries = [];
            draft.data_entries.forEach(entry => {
                const newEntry = {
                    id: Date.now() + Math.random(),
                    tahun: entry.tahun || '',
                    nama_data: entry.nama_data || '',
                    status_perolehan: entry.status_perolehan || '',
                    jenis_sumber: entry.jenis_sumber || '',
                    digunakan_pembangunan: entry.digunakan_pembangunan || '',
                    tingkat_kepuasan: parseInt(entry.tingkat_kepuasan) || 0
                };
                dataEntries.push(newEntry);
            });
            
            // Render dengan delay
            setTimeout(() => {
                dataEntries.forEach(entry => renderDataEntry(entry));
                updateAddButtonState();
            }, 200);
        }

        // Load catatan
        $('#catatan_tambahan').val(draft.catatan_tambahan || '');

        // Switch to last page
        setTimeout(() => {
            switchPage(currentPage);
            showMessage('✅ Melanjutkan survey sebelumnya...', 'success');
        }, 500);
    }

    // Auto-save function
    function autoSave() {
        const formData = new FormData($('#survey-form')[0]);
        formData.append('current_page', currentPage);
        formData.append('session_id', sessionId);
        formData.append('data_entries', JSON.stringify(dataEntries));

        // Prepare checkbox values
        formData.append('jenis_disabilitas', getCheckedValues('jenis_disabilitas[]'));
        formData.append('tujuan_penggunaan', getCheckedValues('tujuan[]'));
        formData.append('jenis_layanan', getCheckedValues('layanan[]'));
        formData.append('sarana_layanan', getCheckedValues('sarana[]'));

        $.ajax({
            url: '{{ route("survey.autosave") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    sessionId = response.session_id;
                    console.log('Auto-saved successfully');
                }
            },
            error: function(xhr) {
                console.error('Auto-save failed:', xhr);
            }
        });
    }

    function initializeForm() {
        renderRatingItems();
        setupFormHandlers();
    }

    // Render rating items
    function renderRatingItems() {
        const container = $('#rating-items-container');
        container.html('');
        
        ratingItems.forEach((item, index) => {
            const itemHtml = `
                <div class="border-l-4 border-blue-400 pl-6 py-4">
                    <h3 class="font-semibold text-gray-800 mb-4">${index + 1}. ${item.title}</h3>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Tingkat Kepentingan</label>
                            <div id="${item.id}_kepentingan_rating" class="star-rating flex gap-0.5 sm:gap-1"></div>
                            <input type="hidden" id="${item.id}_kepentingan" name="${item.id}_kepentingan" value="0" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Tingkat Kepuasan</label>
                            <div id="${item.id}_kepuasan_rating" class="star-rating flex gap-0.5 sm:gap-1"></div>
                            <input type="hidden" id="${item.id}_kepuasan" name="${item.id}_kepuasan" value="0" required>
                        </div>
                    </div>
                </div>
            `;
            container.append(itemHtml);
        });

        setTimeout(() => {
            initializeAllRatings();
        }, 0);
    }

    // Initialize all star ratings
    function initializeAllRatings() {
        ratingItems.forEach(item => {
            initializeStarRating(item.id + '_kepentingan');
            initializeStarRating(item.id + '_kepuasan');
        });
    }

    // Initialize individual star rating
    function initializeStarRating(fieldId) {
        const container = $(`#${fieldId}_rating`);
        if (container.length === 0) return;
        
        container.html('');
        const inputField = $(`#${fieldId}`);
        
        for (let i = 1; i <= 10; i++) {
            const star = $(`<button type="button" class="text-lg sm:text-xl md:text-2xl transition-transform hover:scale-110 cursor-pointer" data-value="${i}">⭐</button>`);
            star.css('opacity', '0.3');
            
            star.on('click', function(e) {
                e.preventDefault();
                inputField.val(i);
                updateStarDisplay(fieldId, i);
            });
            
            star.on('mouseover', function() {
                updateStarDisplay(fieldId, i, true);
            });
            
            container.append(star);
        }
        
        container.on('mouseleave', function() {
            const currentValue = inputField.val();
            updateStarDisplay(fieldId, currentValue);
        });
    }

    // Update star display
    function updateStarDisplay(fieldId, value, isHover = false) {
        const container = $(`#${fieldId}_rating`);
        if (container.length === 0) return;
        
        container.find('button').each(function(index) {
            $(this).css('opacity', index < value ? '1' : '0.3');
        });
    }

    // Setup event handlers
    function setupFormHandlers() {
        document.querySelectorAll('.btn-next').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const nextPage = parseInt(this.dataset.next);
                
                // Validate before moving
                const errors = getValidationErrors(currentPage);
                
                if (errors.length > 0) {
                    showValidationModal('Silakan lengkapi data terlebih dahulu.', errors);
                } else {
                    switchPage(nextPage); // This will auto-save
                }
            });
        });

        $('.btn-prev').on('click', function(e) {
            e.preventDefault();
            const prevPage = parseInt($(this).data('prev'));
            switchPage(prevPage);
        });

        // Quick fill button
        $('#btn-quick-fill').on('click', function(e) {
            e.preventDefault();
            ratingItems.forEach(item => {
                $(`#${item.id}_kepentingan`).val(10);
                $(`#${item.id}_kepuasan`).val(10);
                updateStarDisplay(item.id + '_kepentingan', 10);
                updateStarDisplay(item.id + '_kepuasan', 10);
            });
        });

        // Disability toggle
        $('#disabilitas_ya, #disabilitas_tidak').on('click', function() {
            toggleDisabilityFields();
        });

        // Conditional fields
        $('#pekerjaan').on('change', function() {
            $('#pekerjaan_lainnya').toggleClass('hidden', $(this).val() !== 'Lainnya');
        });

        $('#kategori_instansi').on('change', function() {
            $('#kategori_instansi_lainnya').toggleClass('hidden', $(this).val() !== 'Lainnya');
        });

        $('#tujuan_5').on('change', function() {
            $('#tujuan_lainnya').toggleClass('hidden', !this.checked);
        });

        $('#sarana_6').on('change', function() {
            $('#sarana_lainnya').toggleClass('hidden', !this.checked);
        });

        // Data entries
        $('#btn-add-data').on('click', function(e) {
            e.preventDefault();
            if (dataEntries.length < 3) {
                addDataEntry();
                updateAddButtonState();
            }
        });

        // Form submission
        $('#survey-form').on('submit', function(e) {
            e.preventDefault();
            submitForm();
        });

        // Fill new form button
        $('#btn-fill-new').on('click', function(e) {
            e.preventDefault();
            resetForm();
            switchPage(1);
        });
    }

    // Toggle disability fields
    function toggleDisabilityFields() {
        if ($('#disabilitas_ya').is(':checked')) {
            $('#jenis-disabilitas-container').removeClass('hidden');
        } else {
            $('#jenis-disabilitas-container').addClass('hidden');
            $('input[name="jenis_disabilitas[]"]').prop('checked', false);
        }
    }

    // Switch page with auto-save
    function switchPage(pageNum) {

        if (window.isSubmittingFinal) {
                document.querySelectorAll('.step-content').forEach(el => {
                    el.classList.remove('active');
                    el.style.display = 'none';
                });
                
                const nextPageEl = document.getElementById('page-' + pageNum);
                if(nextPageEl) {
                    nextPageEl.classList.add('active');
                    nextPageEl.style.display = 'block';
                }
                
                currentPage = pageNum;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

        // Auto-save before switching (jika bukan halaman pertama/load awal)
        if (currentPage > 0 && currentPage !== pageNum) {
            autoSave();
        }

        // 1. Sembunyikan semua halaman, tampilkan yang aktif
        document.querySelectorAll('.step-content').forEach(el => {
            el.classList.remove('active');
            el.style.display = 'none';
        });
        
        const nextPageEl = document.getElementById('page-' + pageNum);
        if(nextPageEl) {
            nextPageEl.classList.add('active');
            nextPageEl.style.display = 'block';
        }
        
        // 2. Update variabel global
        currentPage = pageNum;
        
        // Cek dulu elemennya ada atau tidak (untuk layout baru vs lama)
        const pageText = document.getElementById('current-step-text') || document.getElementById('current-page');
        if (pageText) {
            pageText.textContent = pageNum;
        }

        // Update Progress Bar
        const progressPercentages = { 1: 25, 2: 50, 3: 75, 4: 100 };
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.width = progressPercentages[pageNum] + '%';
        }
        
        // Scroll ke atas
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Get validation errors
    function getValidationErrors(page = null) {
        const errors = [];
        const checkPage = page || currentPage;

        if (checkPage === 1 || checkPage === null) {
            if (!$('#nama').val().trim()) errors.push('Nama belum diisi');
            if (!$('#email').val().trim()) errors.push('Email belum diisi');
            if (!$('#nomor_hp').val().trim()) errors.push('Nomor HP belum diisi');
            if (!$('input[name="jenis_kelamin"]:checked').length) errors.push('Jenis Kelamin belum dipilih');
            if (!$('#kelompok_umur').val()) errors.push('Kelompok Umur belum dipilih');
            if (!$('#pendidikan').val()) errors.push('Pendidikan belum dipilih');
            if (!$('#pekerjaan').val()) errors.push('Pekerjaan belum dipilih');
            if (!$('#kategori_instansi').val()) errors.push('Kategori Instansi belum dipilih');
            if (!$('#nama_instansi').val().trim()) errors.push('Nama Instansi belum diisi');
            if (!$('input[name="penyandang_disabilitas"]:checked').length) errors.push('Status Penyandang Disabilitas belum dipilih');

            const disabilitasYa = $('#disabilitas_ya').is(':checked');
            const disabilitasTypeChecked = $('input[name="jenis_disabilitas[]"]:checked').length > 0;
            if (disabilitasYa && !disabilitasTypeChecked) {
                errors.push('Jenis Disabilitas belum dipilih');
            }

            if (!$('input[name="tujuan[]"]:checked').length) errors.push('Tujuan Penggunaan Data belum dipilih');
            if (!$('input[name="layanan[]"]:checked').length) errors.push('Jenis Layanan belum dipilih');
            if (!$('input[name="sarana[]"]:checked').length) errors.push('Sarana Layanan belum dipilih');
        }

        if (checkPage === 2 || checkPage === null) {
            let allRatingsCompleted = true;
            ratingItems.forEach(item => {
                const kepentingan = parseInt($(`#${item.id}_kepentingan`).val() || 0);
                const kepuasan = parseInt($(`#${item.id}_kepuasan`).val() || 0);
                if (kepentingan === 0 || kepuasan === 0) {
                    allRatingsCompleted = false;
                }
            });
            
            if (!allRatingsCompleted) {
                errors.push('Ada rating Kepentingan atau Kepuasan yang belum diisi (16 pertanyaan)');
            }
        }

        if (checkPage === 3 || checkPage === null) {
            if (dataEntries.length === 0) {
                errors.push('Belum ada data yang ditambahkan (minimal 1)');
            }
        }

        return errors;
    }

    // Show validation modal
    function showValidationModal(message, errors) {
        const existingModal = $('#validation-modal');
        if (existingModal.length) existingModal.remove();

        const errorList = errors.map(e => `• ${e}`).join('<br>');
        
        const modal = $(`
            <div id="validation-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl p-8 max-w-2xl mx-auto shadow-2xl max-h-96 overflow-y-auto">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-blue-800 mb-2 flex items-center gap-2">
                            <span class="text-3xl">⚠️</span> Data Belum Lengkap
                        </h2>
                        <p class="text-gray-700 font-medium mb-4">${message}</p>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg mt-4">
                            <div class="text-sm text-red-700">${errorList}</div>
                        </div>
                    </div>
                    <div class="flex gap-3 justify-end mt-6">
                        <button type="button" class="btn-ok-validation px-6 py-3 bg-blue-800 text-white font-semibold rounded-xl hover:bg-blue-900 transition-all">OK</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);

        modal.find('.btn-ok-validation').on('click', function() {
            modal.remove();
        });

        modal.on('click', function(e) {
            if (e.target === modal[0]) modal.remove();
        });
    }

    // Add data entry
    function addDataEntry() {
        const entryId = Date.now();
        const newEntry = {
            id: entryId,
            tahun: '',
            nama_data: '',
            status_perolehan: '',
            jenis_sumber: '',
            digunakan_pembangunan: '',
            tingkat_kepuasan: 0
        };

        dataEntries.push(newEntry);
        renderDataEntry(newEntry);
        updateAddButtonState();
    }

    // Render data entry
    function renderDataEntry(entry) {
        const existingEntry = $(`#entry-${entry.id}`);
        if (existingEntry.length) {
            existingEntry.remove();
        }

        const container = $('#data-entries-container');
        const isDataObtained = entry.status_perolehan === 'Ya, sesuai' || entry.status_perolehan === 'Ya, tidak sesuai';

        const entryHtml = `
            <div id="entry-${entry.id}" class="border-2 border-blue-200 rounded-xl p-6 bg-blue-50">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-800">Data ${dataEntries.indexOf(entry) + 1}</h3>
                    <button type="button" class="btn-remove-entry text-red-600 hover:text-red-800 hover:bg-red-100 p-2 rounded-lg transition-all" data-id="${entry.id}" title="Hapus data">🗑️</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Data <span class="text-red-500">*</span></label>
                        <input type="text" class="nama-data w-full px-3 py-2.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all bg-white text-sm" data-id="${entry.id}" value="${entry.nama_data}" placeholder="Contoh: Jumlah Penduduk">
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tahun <span class="text-red-500">*</span></label>
                            <input type="text" class="tahun w-full px-3 py-2.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all bg-white text-sm" data-id="${entry.id}" value="${entry.tahun}" placeholder="Contoh: 2025">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Apakah data sudah diperoleh? <span class="text-red-500">*</span></label>
                            <select class="status-perolehan w-full px-3 py-2.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all bg-white text-sm" data-id="${entry.id}">
                                <option value="">-- Pilih Status --</option>
                                <option value="Ya, sesuai" ${entry.status_perolehan === 'Ya, sesuai' ? 'selected' : ''}>Ya, sesuai</option>
                                <option value="Ya, tidak sesuai" ${entry.status_perolehan === 'Ya, tidak sesuai' ? 'selected' : ''}>Ya, tidak sesuai</option>
                                <option value="Tidak diperoleh" ${entry.status_perolehan === 'Tidak diperoleh' ? 'selected' : ''}>Tidak diperoleh</option>
                                <option value="Belum diperoleh" ${entry.status_perolehan === 'Belum diperoleh' ? 'selected' : ''}>Belum diperoleh</option>
                            </select>
                        </div>
                    </div>

                    ${isDataObtained ? `
                        <div class="p-4 bg-white rounded-lg space-y-4">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Jenis Sumber Data <span class="text-red-500">*</span></label>
                                    <select class="jenis-sumber w-full px-3 py-2.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all bg-white text-sm" data-id="${entry.id}">
                                        <option value="">-- Pilih Jenis --</option>
                                        <option value="Publikasi" ${entry.jenis_sumber === 'Publikasi' ? 'selected' : ''}>Publikasi</option>
                                        <option value="Website" ${entry.jenis_sumber === 'Website' ? 'selected' : ''}>Website</option>
                                        <option value="Data Mikro" ${entry.jenis_sumber === 'Data Mikro' ? 'selected' : ''}>Data Mikro</option>
                                        <option value="Tabulasi" ${entry.jenis_sumber === 'Tabulasi' ? 'selected' : ''}>Tabulasi</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Digunakan untuk perencanaan pembangunan? <span class="text-red-500">*</span></label>
                                    <select class="digunakan-pembangunan w-full px-3 py-2.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all bg-white text-sm" data-id="${entry.id}">
                                        <option value="">-- Pilih Jawaban --</option>
                                        <option value="Ya" ${entry.digunakan_pembangunan === 'Ya' ? 'selected' : ''}>Ya</option>
                                        <option value="Tidak" ${entry.digunakan_pembangunan === 'Tidak' ? 'selected' : ''}>Tidak</option>
                                        <option value="Tidak Tahu" ${entry.digunakan_pembangunan === 'Tidak Tahu' ? 'selected' : ''}>Tidak Tahu</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Tingkat Kepuasan Kualitas Data (1-10) <span class="text-red-500">*</span></label>
                                <div class="overflow-x-auto pb-2">
                                    <div id="rating-container-${entry.id}" class="flex gap-1 min-w-max"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-2">Dipilih: <span id="kepuasan-value-${entry.id}">${entry.tingkat_kepuasan || '-'}</span></div>
                            </div>
                        </div>
                    ` : `
                        <div class="p-3 bg-amber-50 border-l-4 border-amber-400 rounded-r-lg">
                            <p class="text-sm text-amber-800">ℹ️ Pilih "Ya, sesuai" atau "Ya, tidak sesuai" untuk melanjutkan</p>
                        </div>
                    `}
                </div>
            </div>
        `;
        
        container.append(entryHtml);

        setupEntryListeners(entry.id);

        if (isDataObtained) {
            setTimeout(() => {
                const ratingContainer = $(`#rating-container-${entry.id}`);
                if (ratingContainer.length > 0) {
                    createStarRatingForDataEntry(entry.id, entry.tingkat_kepuasan);
                } else {
                    console.log('Rating container not found for entry:', entry.id);
                }
            }, 300);
        }
        
    }

    // Create star rating for data entry
    function createStarRatingForDataEntry(entryId, currentValue) {
        const container = $(`#rating-container-${entryId}`);
        if (container.length === 0) return;
        
        container.html('');
        const valueDisplay = $(`#kepuasan-value-${entryId}`);
        
        for (let i = 1; i <= 10; i++) {
            const star = $(`<button type="button" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-lg sm:text-xl transition-transform hover:scale-110 cursor-pointer flex-shrink-0" data-value="${i}">⭐</button>`);
            
            star.on('click', function(e) {
                e.preventDefault();
                const entry = dataEntries.find(d => d.id === entryId);
                if (entry) {
                    entry.tingkat_kepuasan = i;
                    if (valueDisplay.length) valueDisplay.text(i);
                    updateStarsForDataEntry(entryId, i);
                }
            });
            
            star.on('mouseover', function() {
                updateStarsForDataEntry(entryId, i);
            });
            
            container.append(star);
        }
        
        container.on('mouseleave', function() {
            const entry = dataEntries.find(d => d.id === entryId);
            const val = entry ? entry.tingkat_kepuasan : 0;
            updateStarsForDataEntry(entryId, val);
        });
    }

    // Update stars for data entry
    function updateStarsForDataEntry(entryId, value) {
        const container = $(`#rating-container-${entryId}`);
        if (container.length === 0) return;
        
        container.find('button').each(function(index) {
            $(this).css('opacity', index < value ? '1' : '0.3');
        });
    }

    // Setup entry listeners
    function setupEntryListeners(entryId) {
        const entryDiv = $(`#entry-${entryId}`);

        entryDiv.find('.tahun').on('input', function() {
            const entry = dataEntries.find(e => e.id === entryId);
            if (entry) entry.tahun = $(this).val();
        });

        entryDiv.find('.nama-data').on('input', function() {
            const entry = dataEntries.find(e => e.id === entryId);
            if (entry) entry.nama_data = $(this).val();
        });

        entryDiv.find('.status-perolehan').on('change', function() {
            const entry = dataEntries.find(e => e.id === entryId);
            if (entry) {
                entry.status_perolehan = $(this).val();
                renderDataEntry(entry);
            }
        });

        entryDiv.find('.jenis-sumber').on('change', function() {
            const entry = dataEntries.find(e => e.id === entryId);
            if (entry) entry.jenis_sumber = $(this).val();
        });

        entryDiv.find('.digunakan-pembangunan').on('change', function() {
            const entry = dataEntries.find(e => e.id === entryId);
            if (entry) entry.digunakan_pembangunan = $(this).val();
        });

        entryDiv.find('.btn-remove-entry').on('click', function() {
            removeDataEntry(entryId);
        });
    }

    // Remove data entry
    function removeDataEntry(entryId) {
        dataEntries = dataEntries.filter(e => e.id !== entryId);
        $(`#entry-${entryId}`).remove();
        updateAddButtonState();
    }

    // Update add button state
    function updateAddButtonState() {
        const btn = $('#btn-add-data');
        const count = dataEntries.length;
        $('#current-data-count').text(count);

        if (count >= 3) {
            btn.prop('disabled', true);
            btn.addClass('opacity-50 cursor-not-allowed');
        } else {
            btn.prop('disabled', false);
            btn.removeClass('opacity-50 cursor-not-allowed');
        }
    }

    // Get checked values
    function getCheckedValues(name) {
        return $(`input[name="${name}"]:checked`).map(function() {
            return $(this).val();
        }).get().join(', ');
    }

    // Submit form
    function submitForm() {
        if (isSubmitting) return;

        const errors = getValidationErrors(null);
        if (errors.length > 0) {
            showValidationModal('Silakan lengkapi semua data sebelum submit.', errors);
            return;
        }

        isSubmitting = true;
        setLoading(true);

        window.isSubmittingFinal = true;

        // Prepare data entries JSON
        $('#data_entries_json').val(JSON.stringify(dataEntries));
        
        if (sessionId) {
            $('<input>').attr({
                type: 'hidden',
                name: 'session_id',
                value: sessionId
            }).appendTo('#survey-form');
        }

        // Prepare checkbox values
        $('input[name="jenis_disabilitas"]').remove();
        const jenisDisabilitas = getCheckedValues('jenis_disabilitas[]');
        $('<input>').attr({
            type: 'hidden',
            name: 'jenis_disabilitas',
            value: jenisDisabilitas
        }).appendTo('#survey-form');

        $('input[name="tujuan_penggunaan"]').remove();
        const tujuanPenggunaan = getCheckedValues('tujuan[]');
        $('<input>').attr({
            type: 'hidden',
            name: 'tujuan_penggunaan',
            value: tujuanPenggunaan
        }).appendTo('#survey-form');

        $('input[name="jenis_layanan"]').remove();
        const jenisLayanan = getCheckedValues('layanan[]');
        $('<input>').attr({
            type: 'hidden',
            name: 'jenis_layanan',
            value: jenisLayanan
        }).appendTo('#survey-form');

        $('input[name="sarana_layanan"]').remove();
        const saranaLayanan = getCheckedValues('sarana[]');
        $('<input>').attr({
            type: 'hidden',
            name: 'sarana_layanan',
            value: saranaLayanan
        }).appendTo('#survey-form');

        // Submit via AJAX
        const formData = new FormData($('#survey-form')[0]);

        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: '{{ route("survey.store") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    sessionId = null; // Clear session
                    switchPage(5);
                    
                    setTimeout(() => {
                        isSubmitting = false;
                        setLoading(false);
                    }, 500);
                } else {
                    showMessage('❌ Gagal menyimpan data: ' + (response.message || 'Unknown error'), 'error');
                    isSubmitting = false;
                    setLoading(false);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Terjadi kesalahan saat menyimpan data';
                
                if (xhr.status === 419) {
                    errorMessage = 'Session expired. Silakan refresh halaman dan coba lagi.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                showMessage('❌ ' + errorMessage, 'error');
                isSubmitting = false;
                setLoading(false);
            }
        });
    }

    // Set loading state
    function setLoading(loading) {
        const btn = $('#submit-btn');
        const text = $('#submit-text');
        const spinner = $('#submit-spinner');
        
        btn.prop('disabled', loading);
        spinner.toggleClass('hidden', !loading);
        text.text(loading ? 'Menyimpan...' : 'Kirim Formulir');
    }

    // Show message
    function showMessage(message, type) {
        const msgEl = $('#form-message');
        msgEl.text(message);
        msgEl.removeClass('hidden bg-green-100 text-green-800 bg-red-100 text-red-800 bg-green-50 text-green-600 bg-red-50 text-red-600 border-green-200 border-red-200');
        
        if (type === 'success') {
            msgEl.addClass('bg-green-50 text-green-600 border border-green-200');
        } else {
            msgEl.addClass('bg-red-50 text-red-600 border border-red-200');
        }
        
        setTimeout(() => {
            msgEl.addClass('hidden');
        }, 5000);
    }

    // Reset form
    function resetForm() {
        $('#survey-form')[0].reset();
        $('#jenis-disabilitas-container').addClass('hidden');
        $('#pekerjaan_lainnya').addClass('hidden');
        $('#kategori_instansi_lainnya').addClass('hidden');
        $('#tujuan_lainnya').addClass('hidden');
        $('#sarana_lainnya').addClass('hidden');
        
        $('#data-entries-container').html('');
        dataEntries = [];
        
        ratingItems.forEach(item => {
            $(`#${item.id}_kepentingan`).val(0);
            $(`#${item.id}_kepuasan`).val(0);
            updateStarDisplay(item.id + '_kepentingan', 0);
            updateStarDisplay(item.id + '_kepuasan', 0);
        });

        updateAddButtonState();
        sessionId = null;
    }
</script>