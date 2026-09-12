<div id="logoutModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 400px; width: 90%;">
        <h3 style="margin: 0 0 12px; font-size: 16px; font-weight: 700; color: var(--navy);">Log Out</h3>
        <p style="margin: 0 0 20px; color: var(--ink);">Do you want to log out?</p>
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button type="button" class="button button-secondary" onclick="closeLogoutModal()">Cancel</button>
            <a href="/logout.php" class="button button-primary" style="text-decoration: none;">Log Out</a>
        </div>
    </div>
</div>

<script>
function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
});
</script>