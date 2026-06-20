function openModal(id) {
    const modal = document.getElementById(id);
    modal?.classList.add('active');
    if (modal) {
        setTimeout(() => initDatePickers(modal), 50);
    }
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('active');
}

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
    }
});

/* ── Flatpickr date/time pickers ── */
function initDatePickers(container = document) {
    if (typeof flatpickr === 'undefined') return;

    container.querySelectorAll('.input-date:not(.flatpickr-input)').forEach(el => {
        flatpickr(el, {
            locale: 'th',
            dateFormat: 'Y-m-d',
            allowInput: true,
        });
    });

    container.querySelectorAll('.input-datetime:not(.flatpickr-input)').forEach(el => {
        flatpickr(el, {
            locale: 'th',
            enableTime: true,
            dateFormat: 'Y-m-d H:i:S',
            time_24hr: true,
            allowInput: true,
        });
    });

    container.querySelectorAll('.input-time:not(.flatpickr-input)').forEach(el => {
        flatpickr(el, {
            locale: 'th',
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i:S',
            time_24hr: true,
            allowInput: true,
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initDatePickers();
    initThemeToggle();
    initQueryHistory();
});

function initThemeToggle() {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('pg_manager_theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('pg_manager_theme', 'dark');
        }
    });
}

function initQueryHistory() {
    document.querySelectorAll('.history-item').forEach(item => {
        item.addEventListener('click', () => {
            const editor = document.querySelector('.sql-editor');
            if (editor && item.dataset.sql) {
                editor.value = item.dataset.sql;
                editor.focus();
            }
        });
    });
}

/* ── Table column builder ── */
function addColumnRow() {
    const container = document.getElementById('columnsContainer');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'column-row';
    row.innerHTML = `
        <input type="text" placeholder="ชื่อคอลัมน์" class="col-name">
        <select class="col-type">
            <option value="INTEGER">INTEGER</option>
            <option value="BIGINT">BIGINT</option>
            <option value="VARCHAR(255)">VARCHAR(255)</option>
            <option value="TEXT">TEXT</option>
            <option value="BOOLEAN">BOOLEAN</option>
            <option value="DATE">DATE</option>
            <option value="TIMESTAMP">TIMESTAMP</option>
            <option value="NUMERIC(10,2)">NUMERIC(10,2)</option>
            <option value="JSONB">JSONB</option>
        </select>
        <label><input type="checkbox" class="col-pk"> PK</label>
        <label><input type="checkbox" class="col-nn"> NOT NULL</label>
        <input type="text" placeholder="Default" class="col-default">
    `;
    container.appendChild(row);
}

function prepareColumns() {
    const rows = document.querySelectorAll('#columnsContainer .column-row');
    const columns = [];

    rows.forEach(row => {
        const name = row.querySelector('.col-name')?.value.trim();
        const type = row.querySelector('.col-type')?.value;
        if (!name) return;

        columns.push({
            name,
            type,
            primary_key: row.querySelector('.col-pk')?.checked || false,
            not_null: row.querySelector('.col-nn')?.checked || false,
            default: row.querySelector('.col-default')?.value || '',
        });
    });

    if (columns.length === 0) {
        alert('กรุณาระบุคอลัมน์อย่างน้อย 1 คอลัมน์');
        return false;
    }

    document.getElementById('columnsJson').value = JSON.stringify(columns);
    return true;
}

/* ── Dynamic edit row fields ── */
function buildFieldHtml(col, fieldPrefix, value, isWhere = false) {
    const name = col.name;
    const inputType = col.input_type || 'string';
    const id = `field_${fieldPrefix}_${name}`.replace(/[^a-z0-9_]/gi, '_');
    const fieldName = `${fieldPrefix}[${name}]`;
    const strVal = value === null || value === undefined ? '' : String(value);
    const label = isWhere ? `${name} (ค่าเดิม)` : name;

    if (isWhere) {
        if (value === null || value === undefined) {
            return `<input type="hidden" name="where_null[]" value="${escapeAttr(name)}">`;
        }
        return `<input type="hidden" name="${fieldName}" value="${escapeAttr(strVal)}">`;
    }

    if (inputType === 'boolean') {
        const checked = ['1', 't', 'true', 'yes'].includes(strVal.toLowerCase());
        const nullOpt = col.nullable === 'YES'
            ? `<option value="" ${strVal === '' ? 'selected' : ''}>NULL</option>` : '';
        return `<div class="form-group">
            <label for="${id}">${escapeHtml(label)}</label>
            <select name="${fieldName}" id="${id}">${nullOpt}
                <option value="true" ${checked ? 'selected' : ''}>true</option>
                <option value="false" ${strVal !== '' && !checked ? 'selected' : ''}>false</option>
            </select>
        </div>`;
    }

    if (inputType === 'date') {
        const dateVal = strVal ? strVal.substring(0, 10) : '';
        return `<div class="form-group">
            <label for="${id}">${escapeHtml(label)} <small class="text-muted">(วันที่)</small></label>
            <input type="text" name="${fieldName}" id="${id}" value="${escapeAttr(dateVal)}"
                   class="input-date" placeholder="เลือกวันที่" autocomplete="off">
        </div>`;
    }

    if (inputType === 'datetime') {
        let dtVal = strVal;
        if (dtVal && !dtVal.includes('T')) {
            dtVal = dtVal.replace(' ', 'T').substring(0, 19);
        }
        return `<div class="form-group">
            <label for="${id}">${escapeHtml(label)} <small class="text-muted">(วันเวลา)</small></label>
            <input type="text" name="${fieldName}" id="${id}" value="${escapeAttr(dtVal)}"
                   class="input-datetime" placeholder="เลือกวันและเวลา" autocomplete="off">
        </div>`;
    }

    if (inputType === 'time') {
        const timeVal = strVal ? strVal.substring(0, 8) : '';
        return `<div class="form-group">
            <label for="${id}">${escapeHtml(label)} <small class="text-muted">(เวลา)</small></label>
            <input type="text" name="${fieldName}" id="${id}" value="${escapeAttr(timeVal)}"
                   class="input-time" placeholder="เลือกเวลา" autocomplete="off">
        </div>`;
    }

    if (inputType === 'json' || inputType === 'text') {
        const tag = inputType === 'json' ? 'JSON' : '';
        return `<div class="form-group">
            <label for="${id}">${escapeHtml(label)}${tag ? ` <small class="text-muted">(${tag})</small>` : ''}</label>
            <textarea name="${fieldName}" id="${id}" rows="3">${escapeHtml(strVal)}</textarea>
        </div>`;
    }

    if (inputType === 'number') {
        return `<div class="form-group">
            <label for="${id}">${escapeHtml(label)}</label>
            <input type="number" step="any" name="${fieldName}" id="${id}" value="${escapeAttr(strVal)}">
        </div>`;
    }

    return `<div class="form-group">
        <label for="${id}">${escapeHtml(label)}</label>
        <input type="text" name="${fieldName}" id="${id}" value="${escapeAttr(strVal)}">
    </div>`;
}

function editRow(rowData) {
    const fields = document.getElementById('editFields');
    if (!fields) return;

    const cols = typeof tableColumns !== 'undefined' ? tableColumns : Object.keys(rowData);
    const metaMap = typeof columnMetaMap !== 'undefined' ? columnMetaMap : {};

    fields.innerHTML = '';

    cols.forEach(colName => {
        const col = metaMap[colName] || { name: colName, input_type: 'string', nullable: 'YES' };
        const val = rowData[colName] ?? null;
        fields.innerHTML += buildFieldHtml(col, 'data', val);
        fields.innerHTML += buildFieldHtml(col, 'where', val, true);
    });

    openModal('editModal');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, '&quot;');
}

document.querySelectorAll('.snippet-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const editor = document.querySelector('.sql-editor');
        if (editor) {
            editor.value = btn.dataset.sql;
            editor.focus();
        }
    });
});

document.querySelectorAll('.sql-editor').forEach(editor => {
    editor.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            editor.closest('form')?.submit();
        }
    });
});
