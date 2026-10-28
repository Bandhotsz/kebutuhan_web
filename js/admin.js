// ========================================
//  ADMIN PANEL
// ========================================

function switchAdminTab(tab) {
  document.querySelectorAll('.admin-tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.admin-nav').forEach(n => n.classList.remove('active'));

  const tabEl = document.getElementById(`admin-tab-${tab}`);
  if (tabEl) tabEl.classList.add('active');

  document.querySelectorAll('.admin-nav').forEach(n => {
    if (n.dataset.tab === tab) n.classList.add('active');
  });

  if (tab === 'dashboard') loadAdminDashboard();
  else if (tab === 'templates') loadAdminTemplates();
  else if (tab === 'orders') loadAdminOrders();
  else if (tab === 'payments') loadAdminPayments();
}

// --- Admin Dashboard ---
async function loadAdminDashboard() {
  const result = await apiCall('admin_stats');
  if (!result || !result.success) return;

  const s = result.stats;
  document.getElementById('stat-revenue').textContent = formatRupiah(s.total_revenue);
  document.getElementById('stat-orders').textContent = s.total_orders;
  document.getElementById('stat-users').textContent = s.total_users;
  document.getElementById('stat-pending').textContent = s.pending_payments;

  // Chart
  renderAdminChart(s.category_stats);

  // Recent orders
  const orders = await apiCall('get_orders');
  if (orders && Array.isArray(orders)) {
    const tbody = document.getElementById('recent-orders-body');
    tbody.innerHTML = '';
    orders.slice(0, 5).forEach(o => {
      tbody.innerHTML += `<tr>
        <td class="fw-bold text-primary small">${escapeHtml(o.id)}</td>
        <td class="small">${escapeHtml(o.user_name)}</td>
        <td class="fw-bold small">${formatRupiah(o.total)}</td>
        <td>${getOrderBadge(o.order_status)}</td>
      </tr>`;
    });
    if (orders.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Belum ada pesanan</td></tr>';
    }
  }
}

function renderAdminChart(catStats) {
  const chart = document.getElementById('admin-chart');
  chart.innerHTML = '';

  const categories = ['Elegan', 'Tradisional', 'Modern'];
  const dataMap = {};
  categories.forEach(c => dataMap[c] = 0);
  if (catStats) {
    catStats.forEach(cs => { dataMap[cs.category] = parseInt(cs.revenue) || 0; });
  }

  const maxVal = Math.max(...Object.values(dataMap), 100000);

  categories.forEach(cat => {
    const val = dataMap[cat] || 0;
    const heightPct = Math.max(5, (val / maxVal) * 80);
    const wrapper = document.createElement('div');
    wrapper.className = 'chart-bar-wrapper';
    wrapper.innerHTML = `
      <div class="chart-bar-fill" style="height:${heightPct}%;" data-value="${formatRupiah(val)}"></div>
      <span class="chart-bar-label">${cat}</span>
    `;
    chart.appendChild(wrapper);
  });
}

// --- Admin Templates ---
async function loadAdminTemplates() {
  const data = await apiCall('get_templates');
  if (data && Array.isArray(data)) {
    state.templates = data;
    renderAdminTemplates();
  }
}

function renderAdminTemplates() {
  const tbody = document.getElementById('admin-templates-body');
  tbody.innerHTML = '';

  if (state.templates.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada template</td></tr>';
    return;
  }

  state.templates.forEach(t => {
    tbody.innerHTML += `<tr>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div style="width:40px;height:40px;border-radius:8px;background:${t.gradient || 'var(--gradient-primary)'};"></div>
          <div><strong>${escapeHtml(t.name)}</strong><br><small class="text-muted">${escapeHtml(t.id)}</small></div>
        </div>
      </td>
      <td><span class="badge bg-primary-subtle text-primary">${escapeHtml(t.category)}</span></td>
      <td class="fw-bold">${formatRupiah(t.price)}</td>
      <td><span class="badge bg-success">Aktif</span></td>
      <td>
        <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditTemplate('${t.id}')"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate('${t.id}')"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`;
  });
}

function openAddTemplateModal() {
  document.getElementById('admin-template-modal-title').textContent = 'Tambah Template Baru';
  document.getElementById('admin-template-form').reset();
  document.getElementById('admin-tmpl-id').value = '';
  openModal('adminTemplateModal');
}

function openEditTemplate(id) {
  const t = state.templates.find(x => x.id === id);
  if (!t) return;

  document.getElementById('admin-template-modal-title').textContent = 'Edit Template';
  document.getElementById('admin-tmpl-id').value = t.id;
  document.getElementById('admin-tmpl-name').value = t.name;
  document.getElementById('admin-tmpl-category').value = t.category;
  document.getElementById('admin-tmpl-price').value = t.price;
  document.getElementById('admin-tmpl-desc').value = t.description || '';
  document.getElementById('admin-tmpl-features').value = t.features || '';
  document.getElementById('admin-tmpl-color').value = t.preview_color || '#d4a373';
  openModal('adminTemplateModal');
}

async function handleSaveTemplate(e) {
  e.preventDefault();
  const id = document.getElementById('admin-tmpl-id').value || `tmpl-${Date.now()}`;
  const color = document.getElementById('admin-tmpl-color').value;

  const data = {
    id,
    name: document.getElementById('admin-tmpl-name').value,
    category: document.getElementById('admin-tmpl-category').value,
    price: parseInt(document.getElementById('admin-tmpl-price').value),
    description: document.getElementById('admin-tmpl-desc').value,
    features: document.getElementById('admin-tmpl-features').value,
    preview_color: color,
    gradient: `linear-gradient(135deg, ${color} 0%, ${lightenColor(color, 40)} 50%, ${lightenColor(color, 70)} 100%)`
  };

  const result = await apiCall('save_template', data);
  if (result && result.success) {
    closeModal('adminTemplateModal');
    showToast('Template berhasil disimpan', 'success');
    loadAdminTemplates();
  } else {
    showToast(result ? result.message : 'Gagal menyimpan', 'danger');
  }
}

async function deleteTemplate(id) {
  if (!confirm('Yakin ingin menghapus template ini?')) return;
  const result = await apiCall('delete_template', { id });
  if (result && result.success) {
    showToast('Template berhasil dihapus', 'warning');
    loadAdminTemplates();
  } else {
    showToast(result ? result.message : 'Gagal menghapus', 'danger');
  }
}

// --- Admin Orders ---
async function loadAdminOrders() {
  const data = await apiCall('get_orders');
  if (data && Array.isArray(data)) {
    renderAdminOrders(data);
  }
}

function renderAdminOrders(orders) {
  const tbody = document.getElementById('admin-orders-body');
  tbody.innerHTML = '';

  if (!orders || orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada pesanan</td></tr>';
    return;
  }

  orders.forEach(o => {
    tbody.innerHTML += `<tr>
      <td class="fw-bold text-primary small">${escapeHtml(o.id)}</td>
      <td><strong>${escapeHtml(o.user_name)}</strong><br><small class="text-muted">${escapeHtml(o.user_email)}</small></td>
      <td><span class="badge bg-primary-subtle text-primary">${escapeHtml(o.template_name)}</span></td>
      <td class="fw-bold">${formatRupiah(o.total)}</td>
      <td>${getPaymentBadge(o.payment_status)}</td>
      <td>
        <select class="form-select form-select-sm" style="width:130px;" onchange="updateOrderStatus('${o.id}', this.value)">
          <option value="Pending" ${o.order_status==='Pending'?'selected':''}>Pending</option>
          <option value="Diproses" ${o.order_status==='Diproses'?'selected':''}>Diproses</option>
          <option value="Aktif" ${o.order_status==='Aktif'?'selected':''}>Aktif</option>
          <option value="Selesai" ${o.order_status==='Selesai'?'selected':''}>Selesai</option>
          <option value="Dibatalkan" ${o.order_status==='Dibatalkan'?'selected':''}>Dibatalkan</option>
        </select>
      </td>
      <td>${getOrderBadge(o.order_status)}</td>
    </tr>`;
  });
}

async function updateOrderStatus(orderId, status) {
  const result = await apiCall('update_order_status', { order_id: orderId, order_status: status });
  if (result && result.success) {
    showToast(`Pesanan ${orderId} diubah ke ${status}`, 'success');
    loadAdminOrders();
  }
}

// --- Admin Payments ---
async function loadAdminPayments() {
  const data = await apiCall('get_orders');
  if (data && Array.isArray(data)) {
    renderAdminPayments(data.filter(o => o.payment_proof));
  }
}

function renderAdminPayments(orders) {
  const tbody = document.getElementById('admin-payments-body');
  tbody.innerHTML = '';

  if (!orders || orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada bukti pembayaran</td></tr>';
    return;
  }

  orders.forEach(o => {
    tbody.innerHTML += `<tr>
      <td class="fw-bold text-primary small">${escapeHtml(o.id)}</td>
      <td><strong>${escapeHtml(o.user_name)}</strong></td>
      <td class="fw-bold">${formatRupiah(o.total)}</td>
      <td><a href="${o.payment_proof}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-image"></i> Lihat</a></td>
      <td>${getPaymentBadge(o.payment_status)}</td>
      <td>
        ${o.payment_status === 'Menunggu Verifikasi' ? `
          <button class="btn btn-sm btn-success me-1" onclick="adminVerifyPayment('${o.id}', 'Terverifikasi')"><i class="bi bi-check"></i> Terima</button>
          <button class="btn btn-sm btn-danger" onclick="adminVerifyPayment('${o.id}', 'Ditolak')"><i class="bi bi-x"></i> Tolak</button>
        ` : '<span class="text-muted small">Sudah diproses</span>'}
      </td>
    </tr>`;
  });
}

async function adminVerifyPayment(orderId, status) {
  const result = await apiCall('update_order_status', { order_id: orderId, payment_status: status });
  if (result && result.success) {
    showToast(`Pembayaran ${orderId} ${status === 'Terverifikasi' ? 'diverifikasi' : 'ditolak'}`, status === 'Terverifikasi' ? 'success' : 'warning');
    loadAdminPayments();
  }
}

// Kept for viewPaymentModal (admin view proof + verify)
async function handleVerifyPayment(status) {
  const orderId = document.getElementById('view-payment-order-id').value;
  await adminVerifyPayment(orderId, status);
  closeModal('viewPaymentModal');
}

document.addEventListener('DOMContentLoaded', async () => {
  if (typeof checkSession === 'function') await checkSession();
  
  if (document.getElementById('section-admin-dashboard')) {
    // Admin init: protect route
    if (!state.user || state.user.role !== 'admin') {
      window.location.href = 'index.html';
    } else {
      switchAdminTab('dashboard');
    }
  }
});
