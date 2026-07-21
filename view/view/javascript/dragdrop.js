
function initializeDragDrop() {
    const TABLE_SELECTOR = '#form-gelir table';
    const ROW_SELECTOR = TABLE_SELECTOR + ' tbody tr';

    let selectedTargetRows = [];
    let sourceRowId = null;

    function clearSelection() {
        $(ROW_SELECTOR + '.row-selected').removeClass('row-selected');
        selectedTargetRows = [];
        sourceRowId = null;
    }

    try {
        $(ROW_SELECTOR).draggable('destroy');
        $(ROW_SELECTOR).droppable('destroy');
    } catch (e) {}

    $(ROW_SELECTOR).draggable({
        revert: "invalid",
        helper: "clone",
        cursor: "grabbing",
        opacity: 0.7,
        handle: ".drag-handle",

        start: function (event, ui) {
            clearSelection();
            sourceRowId = $(this).attr('id');
            ui.helper.data('source-row-id', sourceRowId);

            // Kaynak satırın bolum değerini helper’a ekle
            const sourceBolum = $(this).find('select[name*="[bolum]"]').val();
            ui.helper.data('source-bolum', sourceBolum);

            ui.helper.children().each(function (index) {
                $(this).width($(event.target).children().eq(index).width());
            });

            // Kaynak satıra hareketli kesik çizgi
            $(this).addClass('row-source-dragging');
        },

        stop: function (event, ui) {
            $(this).removeClass('row-source-dragging');
            if (selectedTargetRows.length === 0 && sourceRowId) {
                clearSelection();
            }
        }
    });

    $(ROW_SELECTOR).droppable({
        accept: function (draggable) {
            const sourceBolum = draggable.find('select[name*="[bolum]"]').val();
            const targetBolum = $(this).find('select[name*="[bolum]"]').val();

            // Sadece aynı tür (gelir→gelir, gider→gider) eşleşmesine izin ver
            return sourceBolum === targetBolum;
        },
        tolerance: "pointer",

        over: function (event, ui) {
            const $targetRow = $(this);
            const targetRowId = $targetRow.attr('id');
            const sourceBolum = ui.helper.data('source-bolum');
            const targetBolum = $targetRow.find('select[name*="[bolum]"]').val();

            // Bölüm uyumsuzsa yasak imleci göster
            if (sourceBolum !== targetBolum) {
                $targetRow.css('cursor', 'not-allowed');
                return;
            }

            if (targetRowId === sourceRowId) return;

            if (!selectedTargetRows.includes(targetRowId)) {
                selectedTargetRows.push(targetRowId);
                $targetRow.addClass('row-selected');
            }
        },

        out: function (event, ui) {
            $(this).css('cursor', '');
        },

        drop: function (event, ui) {
            $(this).css('cursor', '');
            $('#' + sourceRowId).removeClass('row-selected');

            if (selectedTargetRows.length === 0) {
                clearSelection();
                return;
            }

            const $sourceRow = $('#' + sourceRowId);
            const sourceBolum = $sourceRow.find('select[name*="[bolum]"]').val();

            // Uyumsuz hedefleri çıkar
            selectedTargetRows = selectedTargetRows.filter(rowId => {
                const targetBolum = $('#' + rowId).find('select[name*="[bolum]"]').val();
                return sourceBolum === targetBolum;
            });

            if (selectedTargetRows.length === 0) {
                alert('Bölüm uyuşmayan satırlara kopyalama yapılmaz.');
                clearSelection();
                return;
            }

            const targetCount = selectedTargetRows.length;
            const confirmationMessage = `Kaynak ${targetCount} satıra kopyalandı ve kayıt edilecek işlemi onaylıyor musunuz?`;

            if (confirm(confirmationMessage)) {
                let successCount = 0;

                selectedTargetRows.forEach(rowId => {
                    const $targetRow = $('#' + rowId);
                    const copiedTotal = copyDataToRow($sourceRow, $targetRow);

                    if (copiedTotal > 0) {
                        successCount++;
                        $targetRow.addClass('row-copied-success');
                        $targetRow.addClass('post-field');
                        setTimeout(() => {
                            $targetRow.removeClass('row-copied-success bg-light-danger');
                        }, 1000);
                    }
                });
                 $('#button-gelir-save').click();
                 alertsuccess(successCount + ' adet satır güncellendi');
               
            } else {
                alerterror('Toplu kopyalama iptal edildi.');
            }

            clearSelection();
        }
    });

    // --- SADECE İSTENEN ALANLAR + HIDDEN inputlar ---
    function copyDataToRow($sourceRow, $targetRow) {
        let copiedCount = 0;
        const sourceBolum = $sourceRow.find('select[name*="[bolum]"]').val();

        // Kaynak gider ise ekstra alan ekle
        const ALLOWED_COPY_FIELDS = (sourceBolum === '1')
            ? ['tur', 'fon', 'gelir_sekli', 'proje', 'birim',]
            : ['tur', 'fon', 'gelir_sekli', 'proje', 'birim', 'gider_category'];

            $sourceRow.find('input, select, textarea').each(function () {
            const $sourceInput = $(this);
            const sourceValue = $sourceInput.val();
            const sourceName = $sourceInput.attr('name');

            if (!sourceName) return;
            const nameKeyMatch = sourceName.match(/\[([^\]]+)\]$/);
            if (!nameKeyMatch) return;
            const nameKey = nameKeyMatch[1];

            if (!ALLOWED_COPY_FIELDS.includes(nameKey)) return;

            const $targetInput = $targetRow.find(`[name$="[${nameKey}]"]`);
            if ($targetInput.length && $targetInput.val().trim() === '') {
                $targetInput.val(sourceValue).trigger('change');

                const sourceHiddenName = `${nameKey}_id`;
                const $sourceHiddenInput = $sourceRow.find(`input[name$="[${sourceHiddenName}]"]`);

                if ($sourceHiddenInput.length) {
                    const sourceHiddenValue = $sourceHiddenInput.val();
                    const $targetHiddenInput = $targetRow.find(`input[name$="[${sourceHiddenName}]"]`);
                    if ($targetHiddenInput.length && $targetHiddenInput.val().trim() === '') {
                        $targetHiddenInput.val(sourceHiddenValue);
                    }
                }

                const $targetSpan = $targetInput.closest('td').find('span:first');
                if ($targetSpan.length) {
                    let spanText = $targetInput.is('select')
                        ? $targetInput.find('option:selected').text()
                        : sourceValue;
                    $targetSpan.text(spanText || '-');
                }

                copiedCount++;
            }
        });

        return copiedCount;
         
    }
}
