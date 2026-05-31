/**
 * SmartCampus — Notifications Admin v1
 * Adapté de SmartNotifs — pointe vers admin_notifications_api.php + admin_chat_api.php
 * L'admin reçoit des notifs sur les nouveaux messages de tous les utilisateurs
 */

const AdminNotifs = (() => {
  const BASE = 'http://localhost:8888/Smart_Campus/Code';
  const API  = `${BASE}/admin/php/admin_notifications_api.php`;

  let _userId  = null;
  let _panel   = null;
  let _cache   = [];
  let _seenSet = new Set();

  /* ─── API helpers ─────────────────────────────────────────── */

  async function _apiGet(params) {
    const url = API + '?' + new URLSearchParams(params);
    const r   = await fetch(url);
    return r.json();
  }

  async function _apiPost(action, body) {
    const r = await fetch(API + '?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return r.json();
  }

  /* ─── Chargement BDD ────────────────────────────────────────── */

  async function _loadFromDB() {
    try {
      const data = await _apiGet({ action: 'get', id_user: _userId });
      if (data.success) _cache = data.data || [];

      const seen = await _apiGet({ action: 'get_seen', id_user: _userId });
      if (seen.success) _seenSet = new Set(seen.seen);
    } catch (e) {}
  }

  function _isSeen(dedupId) { return _seenSet.has(dedupId); }

  /* ─── Ajout ─────────────────────────────────────────────────── */

  async function add(type, titre, corps, data = {}) {
    const dedupId = data.dedupId || null;
    if (dedupId && _isSeen(dedupId)) return;
    try {
      const res = await _apiPost('add', {
        id_user: _userId, type, titre, corps, dedup_id: dedupId
      });
      if (res.success && res.inserted > 0) {
        await _loadFromDB();
        _updateBadge();
      }
      if (dedupId) _seenSet.add(dedupId);
    } catch (e) {}
  }

  /* ─── Suppression ─────────────────────────────────────────── */

  async function _remove(id_notification) {
    try {
      await _apiPost('delete', { id_user: _userId, id_notification });
      _cache = _cache.filter(n => n.id_notification !== id_notification);
      _updateBadge();
    } catch (e) {}
  }

  async function markAllRead() {
    try {
      await _apiPost('clear_all', { id_user: _userId });
      const seen = await _apiGet({ action: 'get_seen', id_user: _userId });
      if (seen.success) _seenSet = new Set(seen.seen);
      _cache = [];
      _updateBadge();
    } catch (e) {}
  }

  function countUnread() { return _cache.filter(n => !n.lue).length; }

  /* ─── Badge ─────────────────────────────────────────────────── */

  function _updateBadge() {
    const count = countUnread();
    document.querySelectorAll('.notif-btn').forEach(btn => {
      btn.querySelector('.notif-count-badge')?.remove();
      const dot = btn.querySelector('.notif-dot');
      if (count > 0) {
        const badge = document.createElement('span');
        badge.className = 'notif-count-badge';
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.cssText = `
          position:absolute;top:-5px;right:-5px;
          background:#ef4444;color:#fff;
          font-size:10px;font-weight:700;
          min-width:18px;height:18px;
          border-radius:9px;padding:0 4px;
          display:flex;align-items:center;justify-content:center;
          border:2px solid #fff;pointer-events:none;
          font-family:'DM Sans',sans-serif;line-height:1;
        `;
        btn.style.position = 'relative';
        btn.appendChild(badge);
        if (dot) dot.style.display = 'block';
      } else {
        if (dot) dot.style.display = 'none';
      }
    });
  }

  /* ─── Auto-clear selon la page ──────────────────────────────── */

  const ADMIN_PAGE_CLEARS = {
    'admin_messages.html': ['message_admin'],
  };

  async function _autoClearCurrentPage() {
    const page  = window.location.pathname.split('/').pop();
    const types = ADMIN_PAGE_CLEARS[page];
    if (types && types.length > 0) {
      try {
        await _apiPost('clear_types', { id_user: _userId, types });
        const seen = await _apiGet({ action: 'get_seen', id_user: _userId });
        if (seen.success) _seenSet = new Set(seen.seen);
        _cache = _cache.filter(n => !types.includes(n.type));
        _updateBadge();
      } catch (e) {}
    }
  }

  /* ─── Panel UI ────────────────────────────────────────────── */

  const ADMIN_NOTIF_URLS = {
    message_admin: 'admin_messages.html',
  };

  const ICON_MAP = {
    message_admin: '✉️',
  };

  function _buildPanel() {
    if (_panel) { _panel.remove(); _panel = null; }
    const all = _cache;
    _panel = document.createElement('div');
    _panel.id = 'adminNotifPanel';
    _panel.style.cssText = `
      position:fixed;top:70px;right:16px;z-index:9999;
      width:360px;max-height:500px;
      background:#fff;border:1px solid #e2e8f0;
      border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);
      display:flex;flex-direction:column;overflow:hidden;
      font-family:'DM Sans',sans-serif;
    `;

    const unread = all.filter(n => !n.lue).length;
    const header = document.createElement('div');
    header.style.cssText = `
      padding:14px 16px 10px;border-bottom:1px solid #e2e8f0;
      display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
      background:linear-gradient(135deg,#f59e0b,#ef4444);
    `;
    header.innerHTML = `
      <span style="font-weight:700;font-size:15px;color:#fff">🔔 Notifications Admin
        ${unread > 0 ? `<span style="background:rgba(255,255,255,.25);color:#fff;font-size:10px;font-weight:700;border-radius:9px;padding:1px 7px;margin-left:6px;vertical-align:middle">${unread}</span>` : ''}
      </span>
      <div style="display:flex;gap:8px;align-items:center">
        ${all.length > 0 ? `<button id="adminMarkAllReadBtn" style="font-size:11px;color:#fff;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.15);cursor:pointer;font-weight:600;padding:3px 8px;border-radius:6px">Tout effacer</button>` : ''}
        <button id="closeAdminNotifPanel" style="border:none;background:rgba(255,255,255,.15);cursor:pointer;font-size:16px;color:#fff;width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center">✕</button>
      </div>
    `;
    _panel.appendChild(header);

    const body = document.createElement('div');
    body.style.cssText = `flex:1;overflow-y:auto;padding:6px 0;`;

    if (all.length === 0) {
      body.innerHTML = `<div style="padding:32px 16px;text-align:center;color:#94a3b8;">
        <div style="font-size:36px;margin-bottom:10px">🔕</div>
        <div style="font-size:14px;font-weight:600;color:#64748b">Aucune notification</div>
        <div style="font-size:12px;margin-top:4px">Vous êtes à jour !</div>
      </div>`;
    } else {
      all.forEach(notif => {
        const item = document.createElement('div');
        const icon = ICON_MAP[notif.type] || '🔔';
        const bg   = notif.lue ? 'transparent' : '#fff7ed';
        const url  = ADMIN_NOTIF_URLS[notif.type] || '#';

        item.style.cssText = `
          display:flex;gap:12px;padding:11px 16px;
          background:${bg};cursor:pointer;
          border-bottom:1px solid #f1f5f9;
          transition:background .15s;
        `;
        item.innerHTML = `
          <div style="font-size:20px;flex-shrink:0;margin-top:2px">${icon}</div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:${notif.lue?'500':'700'};color:#0f172a;margin-bottom:2px">${notif.titre}</div>
            <div style="font-size:12px;color:#475569;line-height:1.4">${notif.corps}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px">${_formatDate(notif.date_creation)}</div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
            ${!notif.lue ? `<div style="width:8px;height:8px;background:#f59e0b;border-radius:50%;margin-top:6px"></div>` : ''}
            <span style="font-size:10px;color:#f59e0b;margin-top:auto">→</span>
          </div>
        `;
        item.addEventListener('click', async () => {
          await _remove(notif.id_notification);
          closePanel();
          window.location.href = url;
        });
        item.addEventListener('mouseenter', () => { item.style.background = '#fef3c7'; });
        item.addEventListener('mouseleave', () => { item.style.background = notif.lue ? 'transparent' : '#fff7ed'; });
        body.appendChild(item);
      });
    }
    _panel.appendChild(body);
    document.body.appendChild(_panel);

    document.getElementById('closeAdminNotifPanel')?.addEventListener('click', closePanel);
    document.getElementById('adminMarkAllReadBtn')?.addEventListener('click', async () => {
      await markAllRead();
      closePanel();
    });

    setTimeout(() => { document.addEventListener('click', _outsideClick); }, 50);
  }

  function _outsideClick(e) {
    if (_panel && !_panel.contains(e.target) && !e.target.closest('.notif-btn')) closePanel();
  }

  function openPanel()  { if (_panel) { closePanel(); return; } _buildPanel(); }
  function closePanel() { _panel?.remove(); _panel = null; document.removeEventListener('click', _outsideClick); }

  function _formatDate(iso) {
    const d       = new Date(iso);
    const diffMin = Math.floor((Date.now() - d) / 60000);
    if (diffMin < 1)  return "À l'instant";
    if (diffMin < 60) return `Il y a ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24)   return `Il y a ${diffH}h`;
    const diffD = Math.floor(diffH / 24);
    if (diffD === 1)  return 'Hier';
    if (diffD < 7)    return `Il y a ${diffD} jours`;
    return d.toLocaleDateString('fr-FR');
  }

  /* ─── Vérification des messages (toutes les 2 min) ─────────── */

  async function _checkMessages(user) {
    try {
      const data = await (await fetch(
        `${BASE}/admin/php/admin_chat_api.php?action=get_unread&id_user=${user.id_user}`
      )).json();

      const convs = data.data || [];
      if (!Array.isArray(convs)) return;

      for (const conv of convs) {
        const unread = parseInt(conv.unread_count || 0);
        if (unread <= 0) continue;

        const dedupId = `msg_admin_${conv.id_user}_${conv.last_date}`;
        const nom     = `${conv.prenom || ''} ${conv.nom || ''}`.trim();
        const roleLabel = conv.role === 'enseignant' ? 'Enseignant' : conv.role === 'etudiant' ? 'Étudiant' : 'Admin';
        const preview = conv.last_message
          ? conv.last_message.slice(0, 80) + (conv.last_message.length > 80 ? '…' : '')
          : 'Vous avez un nouveau message.';

        await add('message_admin',
          `Message de ${nom} (${roleLabel})`,
          `${unread > 1 ? `${unread} nouveaux messages — ` : ''}${preview}`,
          { dedupId });
      }
    } catch (e) {}
  }

  /* ─── Init ─────────────────────────────────────────────────── */

  async function init(user) {
    _userId = user.id_user;

    await _loadFromDB();
    await _autoClearCurrentPage();
    _updateBadge();

    // Brancher le bouton notif
    document.querySelectorAll('.notif-btn').forEach(btn => {
      btn.addEventListener('click', e => { e.stopPropagation(); openPanel(); });
    });

    // Premier check rapide, puis toutes les 2 min
    setTimeout(() => _checkMessages(user), 1500);
    setInterval(() => _checkMessages(user), 2 * 60 * 1000);
  }

  return { init, add, markAllRead, countUnread, openPanel, closePanel };
})();
