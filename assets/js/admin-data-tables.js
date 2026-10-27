(() => {
  'use strict';
  const tables = document.querySelectorAll('.admin-shell table.table');
  if (!tables.length) return;
  let serial = 0;
  const normalized = value => (value || '').replace(/\s+/g, ' ').trim();
  const element = (tag, className, label) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (label) node.textContent = label;
    return node;
  };
  for (const table of tables) {
    const headings = Array.from(table.tHead?.rows[0]?.cells || []);
    const body = table.tBodies[0];
    if (!headings.length || !body) continue;
    const scrollRegion = table.closest('.table-responsive, .traffic-log-scroll');
    if (scrollRegion && !scrollRegion.hasAttribute('tabindex')) scrollRegion.tabIndex = 0;
    if (table.dataset.tableTools === 'off') continue;
    const rows = Array.from(body.rows).filter(row => row.cells.length === headings.length);
    if (!rows.length) continue;
    // Single placeholder rows are messages, not records.
    if (rows.length === 1 && rows[0].querySelector('[colspan]')) continue;
    const number = ++serial;
    const controls = element('div', 'table-tools no-print');
    const searchLabel = element('label', 'table-tools-field');
    searchLabel.append(element('span', '', 'Cari pada tabel'));
    const search = element('input', 'form-control form-control-sm');
    search.type = 'search';
    search.placeholder = 'Cari baris yang dimuat';
    search.setAttribute('aria-label', 'Cari pada tabel ' + number);
    searchLabel.append(search);
    controls.append(searchLabel);
    const sizeLabel = element('label', 'table-tools-field table-tools-size');
    sizeLabel.append(element('span', '', 'Baris per halaman'));
    const sizeSelect = element('select', 'form-select form-select-sm');
    for (const count of [10, 25, 50, 100]) {
      const option = new Option(String(count), String(count), false, count === 10);
      sizeSelect.add(option);
    }
    sizeLabel.append(sizeSelect);
    controls.append(sizeLabel);
    const facets = [];
    const allowed = /kelas|status|kesulitan|jenis|modul|pantauan|peran|pengunjung|perangkat|bot|akses/i;
    headings.forEach((head, index) => {
      if (facets.length >= 3 || !allowed.test(normalized(head.textContent))) return;
      const values = [...new Set(rows.map(row => normalized(row.cells[index]?.textContent)).filter(Boolean))];
      if (values.length < 2 || values.length > 15) return;
      const label = element('label', 'table-tools-field');
      label.append(element('span', '', normalized(head.textContent)));
      const select = element('select', 'form-select form-select-sm');
      select.setAttribute('aria-label', 'Filter ' + normalized(head.textContent) + ' pada tabel ' + number);
      select.add(new Option('Semua', ''));
      for (const value of values.sort((a, b) => a.localeCompare(b, 'id'))) select.add(new Option(value, value));
      label.append(select);
      controls.append(label);
      facets.push({index, select});
    });
    const footer = element('div', 'table-tools-footer no-print');
    const summary = element('span', 'small text-secondary');
    summary.setAttribute('aria-live', 'polite');
    const pagination = element('nav', 'table-tools-pages');
    pagination.setAttribute('aria-label', 'Halaman tabel ' + number);
    footer.append(summary, pagination);
    const wrapper = table.closest('.table-responsive, .traffic-log-scroll') || table.parentElement;
    if (wrapper.classList.contains('d-none')) {
      controls.classList.add('d-none', 'd-md-flex');
      footer.classList.add('d-none', 'd-md-flex');
    }
    wrapper.parentElement.insertBefore(controls, wrapper);
    wrapper.after(footer);
    let page = 1;
    let visible = [];
    const render = () => {
      const term = normalized(search.value).toLocaleLowerCase('id');
      visible = Array.from(body.rows).filter(row => row.cells.length === headings.length).filter(row => {
        if (term && !normalized(row.textContent).toLocaleLowerCase('id').includes(term)) return false;
        return facets.every(({index, select}) => !select.value || normalized(row.cells[index]?.textContent) === select.value);
      });
      const size = Number(sizeSelect.value);
      const pages = Math.max(1, Math.ceil(visible.length / size));
      page = Math.min(page, pages);
      const shown = new Set(visible.slice((page - 1) * size, page * size));
      for (const row of body.rows) row.hidden = !shown.has(row);
      const first = visible.length ? (page - 1) * size + 1 : 0;
      const last = Math.min(page * size, visible.length);
      summary.textContent = 'Menampilkan ' + first + '–' + last + ' dari ' + visible.length + ' baris yang dimuat';
      pagination.replaceChildren();
      const button = (label, target, disabled, current) => {
        const item = element('button', 'btn btn-sm ' + (current ? 'btn-primary' : 'btn-outline-secondary'), label);
        item.type = 'button';
        item.disabled = disabled;
        if (current) item.setAttribute('aria-current', 'page');
        item.addEventListener('click', () => { page = target; render(); });
        pagination.append(item);
      };
      button('‹', page - 1, page <= 1, false);
      const starts = Math.max(1, Math.min(page - 2, pages - 4));
      for (let value = starts; value <= Math.min(pages, starts + 4); value++) button(String(value), value, false, value === page);
      button('›', page + 1, page >= pages, false);
    };
    search.addEventListener('input', () => { page = 1; render(); });
    sizeSelect.addEventListener('change', () => { page = 1; render(); });
    for (const facet of facets) facet.select.addEventListener('change', () => { page = 1; render(); });
    if (body.hasAttribute('data-live-table')) new MutationObserver(() => render()).observe(body, {childList: true});
    render();
  }
})();
