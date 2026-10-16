(() => {
  const list = document.getElementById('section-sort-list');
  if (!list) return;
  const status = document.getElementById('section-sort-status');
  let busy = false;
  let drag = null;
  const rows = () => [...list.querySelectorAll('[data-section-id]')];
  const restore = previous => previous.forEach(row => list.appendChild(row));
  const ids = items => items.map(row => Number(row.dataset.sectionId));
  async function save(previous) {
    if (JSON.stringify(ids(previous)) === JSON.stringify(ids(rows()))) return;
    busy = true;
    list.setAttribute('aria-busy', 'true');
    status.textContent = 'Salvando nova ordem…';
    try {
      const response = await fetch(list.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin',
        body: new URLSearchParams({csrf: list.dataset.csrf, order: JSON.stringify(ids(rows()))})
      });
      const result = await response.json();
      if (!response.ok || !result.saved) throw new Error(result.error || 'Não foi possível salvar.');
      rows().forEach((row, index) => { row.querySelector('[data-position]').textContent = String(index + 1); });
      status.textContent = 'Ordem salva. A página inicial já usa a nova sequência.';
    } catch (error) {
      restore(previous);
      status.textContent = error.message || 'Falha ao salvar. A ordem anterior foi restaurada.';
    } finally { busy = false; list.removeAttribute('aria-busy'); }
  }
  list.addEventListener('pointerdown', event => {
    const handle = event.target.closest('.drag-handle');
    if (!handle || busy || (event.pointerType === 'mouse' && event.button !== 0)) return;
    event.preventDefault();
    const row = handle.closest('[data-section-id]');
    drag = {row, previous: rows(), pointer: event.pointerId, handle};
    row.classList.add('dragging');
    handle.setPointerCapture(event.pointerId);
    status.textContent = 'Arraste para a posição desejada e solte para salvar.';
  });
  window.addEventListener('pointermove', event => {
    if (!drag || event.pointerId !== drag.pointer) return;
    const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-section-id]');
    if (target && list.contains(target) && target !== drag.row) {
      const rect = target.getBoundingClientRect();
      list.insertBefore(drag.row, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
    }
    if (event.clientY < 70) window.scrollBy(0, -12);
    if (event.clientY > window.innerHeight - 70) window.scrollBy(0, 12);
  });
  const finish = (event, cancel) => {
    if (!drag || event.pointerId !== drag.pointer) return;
    const current = drag; drag = null;
    current.row.classList.remove('dragging');
    if (current.handle.hasPointerCapture(event.pointerId)) current.handle.releasePointerCapture(event.pointerId);
    if (cancel) { restore(current.previous); status.textContent = 'Ordenação cancelada.'; }
    else save(current.previous);
  };
  window.addEventListener('pointerup', event => finish(event, false));
  window.addEventListener('pointercancel', event => finish(event, true));
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-move]');
    if (!button || busy || drag) return;
    const row = button.closest('[data-section-id]');
    const previous = rows();
    if (button.dataset.move === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
    if (button.dataset.move === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
    save(previous);
  });
})();
