function openEditEventModal() { openModal('editEventModal'); }
function submitEditEvent() {
    const fd = new FormData(document.getElementById('editEventForm'));
    fd.append('action', 'edit_event');
    fetch('api/api_room_manage.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); });
}
