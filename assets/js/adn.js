async function friendAction(action) {
    const res = await fetch('api/api_friends.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams({ action, target_id: ADN_TARGET_ID })
    });
    const d = await res.json();
    if (d.ok) location.reload();
}
