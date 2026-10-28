// ========================================
//  NikahKita - Wedding Invitation E-Commerce
//  Main Application Logic
// ========================================

// --- APP STATE ---
let state = {
  user: null,       // logged in user object
  templates: [],    // wedding invitation templates
  orders: [],       // user's orders
  invitations: [],  // user's invitations
  currentSection: 'home',
  filterCategory: 'All',
  sortBy: 'featured',
  csrfToken: null
};

// --- API HELPER ---
async function apiCall(action, body = null, isFormData = false) {
  try {
    const opts = { method: body ? 'POST' : 'GET' };
    opts.headers = {};
    if (state.csrfToken) {
      opts.headers['X-CSRF-Token'] = state.csrfToken;
    }
    if (body && !isFormData) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    } else if (body && isFormData) {
      opts.body = body; // FormData object
    }
    const res = await fetch(`${API_BASE_URL}?action=${action}`, opts);
    const data = await res.json();
    if (data && data.csrf_token) {
      state.csrfToken = data.csrf_token;
    }
    return data;
  } catch (err) {
    console.error(`API Error [${action}]:`, err);
    showToast('Gagal terhubung ke server', 'danger');
    return null;
  }
}

// --- TOAST NOTIFICATIONS ---
function showToast(message, type = 'success') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `custom-toast toast-${type}`;

  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  toast.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i><span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// --- FORMAT RUPIAH ---
function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0
  }).format(value);
}

// --- ESCAPE HTML (XSS PREVENTION) ---
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// --- MODAL HELPERS ---
function openModal(id) {
  const modal = new bootstrap.Modal(document.getElementById(id));
  modal.show();
}

function closeModal(id) {
  const modalEl = document.getElementById(id);
  const modal = bootstrap.Modal.getInstance(modalEl);
  if (modal) modal.hide();
}

// --- SECTION NAVIGATION ---
function showSection(section) {
  // Auth guard for protected sections
  if (['my-orders', 'my-invitations'].includes(section) && !state.user) {
    showToast('Silakan login terlebih dahulu', 'warning');
    openModal('loginModal');
    return;
  }


  state.currentSection = section;
  document.querySelectorAll('.section-page').forEach(s => s.classList.remove('active'));
  const target = document.getElementById(`section-${section}`);
  if (target) target.classList.add('active');

  // Update active nav
  document.querySelectorAll('#nav-links-buyer .nav-link').forEach(link => link.classList.remove('active'));

  // Load section data
  if (section === 'home') {
    // nothing extra
  } else if (section === 'catalog') {
    loadTemplates();
  } else if (section === 'my-orders') {
    loadMyOrders();
  } else if (section === 'my-invitations') {
    loadMyInvitations();


  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ========================================
//  AUTH SYSTEM
// ========================================

async function handleRegister(e) {
  e.preventDefault();
  const name = document.getElementById('reg-name').value;
  const email = document.getElementById('reg-email').value;
  const phone = document.getElementById('reg-phone').value;
  const password = document.getElementById('reg-password').value;

  const result = await apiCall('register', { name, email, phone, password });
  if (!result) return;

  if (result.success) {
    closeModal('registerModal');
    document.getElementById('otp-email').value = email;

    // Show OTP in dev mode
    if (result.otp_preview) {
      document.getElementById('otp-preview-code').textContent = result.otp_preview;
      document.getElementById('otp-preview-box').style.display = 'block';
    }

    openModal('otpModal');
    showToast(result.message, 'success');
    document.getElementById('register-form').reset();
  } else {
    showToast(result.message, 'danger');
  }
}

async function handleVerifyOTP(e) {
  e.preventDefault();
  const email = document.getElementById('otp-email').value;
  const otp = document.getElementById('otp-input').value;

  const result = await apiCall('verify_otp', { email, otp });
  if (!result) return;

  if (result.success) {
    closeModal('otpModal');
    showToast(result.message, 'success');
    openModal('loginModal');
  } else {
    showToast(result.message, 'danger');
  }
}

async function handleResendOTP() {
  const email = document.getElementById('otp-email').value;
  if (!email) { showToast('Email tidak ditemukan', 'danger'); return; }

  const result = await apiCall('resend_otp', { email });
  if (!result) return;

  if (result.success) {
    if (result.otp_preview) {
      document.getElementById('otp-preview-code').textContent = result.otp_preview;
      document.getElementById('otp-preview-box').style.display = 'block';
    }
    showToast(result.message, 'success');
  } else {
    showToast(result.message, 'danger');
  }
}

async function handleLogin(e) {
  e.preventDefault();
  const email = document.getElementById('login-email').value;
  const password = document.getElementById('login-password').value;

  const result = await apiCall('login', { email, password });
  if (!result) return;

  if (result.success) {
    state.user = result.user;
    closeModal('loginModal');
    updateAuthUI();
    showToast(`Selamat datang, ${state.user.name}!`, 'success');
    document.getElementById('login-form').reset();
  } else {
    if (result.need_verify) {
      closeModal('loginModal');
      document.getElementById('otp-email').value = email;
      openModal('otpModal');
    }
    showToast(result.message, 'danger');
  }
}

async function handleGoogleLogin(response) {
  if (!response || !response.credential) {
    showToast('Login dengan Google gagal', 'danger');
    return;
  }

  const result = await apiCall('google_login', { credential: response.credential });
  if (!result) return;

  if (result.success) {
    state.user = result.user;
    closeModal('loginModal');
    closeModal('registerModal');
    updateAuthUI();
    showToast(`Selamat datang, ${state.user.name}!`, 'success');
  } else {
    showToast(result.message, 'danger');
  }
}

async function handleForgotPassword(e) {
  e.preventDefault();
  const email = document.getElementById('forgot-email').value;

  const btn = document.getElementById('btn-forgot-password');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim...';

  const result = await apiCall('forgot_password', { email });
  
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-send"></i> Kirim OTP';

  if (!result) return;

  if (result.success) {
    closeModal('forgotPasswordModal');
    document.getElementById('reset-email').value = email;
    
    if (result.otp_preview) {
      document.getElementById('forgot-otp-preview-code').textContent = result.otp_preview;
      document.getElementById('forgot-otp-preview-box').style.display = 'block';
    } else {
      document.getElementById('forgot-otp-preview-box').style.display = 'none';
    }

    openModal('resetPasswordModal');
    showToast(result.message, 'success');
    document.getElementById('forgot-password-form').reset();
  } else {
    showToast(result.message, 'danger');
  }
}

async function handleResetPassword(e) {
  e.preventDefault();
  const email = document.getElementById('reset-email').value;
  const otp = document.getElementById('reset-otp-input').value;
  const new_password = document.getElementById('reset-new-password').value;

  const result = await apiCall('reset_password', { email, otp, new_password });
  if (!result) return;

  if (result.success) {
    closeModal('resetPasswordModal');
    showToast(result.message, 'success');
    openModal('loginModal');
    document.getElementById('reset-password-form').reset();
  } else {
    showToast(result.message, 'danger');
  }
}

async function handleLogout() {
  await apiCall('logout');
  state.user = null;
  updateAuthUI();
  showSection('home');
  showToast('Berhasil keluar', 'info');
}

function updateAuthUI() {
  const guestBtns = document.getElementById('btn-group-guest');
  const userBtns = document.getElementById('btn-group-user');
  const adminLink = document.getElementById('admin-menu-link');

  if (state.user) {
    guestBtns.classList.add('d-none');
    userBtns.classList.remove('d-none');
    document.getElementById('user-display-name').textContent = state.user.name;

    if (state.user.role === 'admin') {
      adminLink.classList.remove('d-none');
    } else {
      adminLink.classList.add('d-none');
    }
  } else {
    guestBtns.classList.remove('d-none');
    userBtns.classList.add('d-none');
    adminLink.classList.add('d-none');
  }
}

async function checkSession() {
  const result = await apiCall('check_session');
  if (result && result.success) {
    state.user = result.user;
    updateAuthUI();
  }
}

// ========================================
//  TEMPLATE CATALOG
// ========================================

async function loadTemplates() {
  const data = await apiCall('get_templates');
  if (data && Array.isArray(data)) {
    state.templates = data;
    renderTemplates();
  }
}

function filterTemplates(category) {
  state.filterCategory = category;
  document.querySelectorAll('.btn-filter').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.category === category);
  });
  renderTemplates();
}

function handleSort(criteria) {
  state.sortBy = criteria;
  renderTemplates();
}

function renderTemplates() {
  const grid = document.getElementById('template-grid');
  grid.innerHTML = '';

  let filtered = [...state.templates];

  if (state.filterCategory !== 'All') {
    filtered = filtered.filter(t => t.category === state.filterCategory);
  }

  if (state.sortBy === 'price-low') {
    filtered.sort((a, b) => a.price - b.price);
  } else if (state.sortBy === 'price-high') {
    filtered.sort((a, b) => b.price - a.price);
  }

  if (filtered.length === 0) {
    grid.innerHTML = `<div class="col-12"><div class="empty-state"><i class="bi bi-search"></i><h5>Template Tidak Ditemukan</h5><p>Coba ubah filter kategori Anda</p></div></div>`;
    return;
  }

  filtered.forEach(tmpl => {
    const features = tmpl.features ? tmpl.features.split(',').slice(0, 3) : [];
    const featureTags = features.map(f => `<span class="template-feature-tag">${f.trim()}</span>`).join('');

    const catBadgeClass = tmpl.category === 'Elegan' ? 'bg-warning text-dark' :
                          tmpl.category === 'Tradisional' ? 'bg-danger' : 'bg-info';

    const templateImages = {
      'tmpl-001': 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=600&q=80', // Sakura Blossom
      'tmpl-002': 'https://images.unsplash.com/photo-1584447128309-b66b7a4d1b63?auto=format&fit=crop&w=600&q=80', // Batik Nusantara
      'tmpl-003': 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=600&q=80', // Minimalist White
      'tmpl-004': 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=600&q=80', // Rose Gold Luxury
      'tmpl-005': 'https://images.unsplash.com/photo-1596708688757-550a6db15ab5?auto=format&fit=crop&w=600&q=80', // Wayang Kulit / Javanese Art
      'tmpl-006': 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?auto=format&fit=crop&w=600&q=80', // Galaxy Dreams
      'tmpl-007': 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=600&q=80'  // 3D Motion Luxury (Miftah & Agus)
    };
    const bgImage = templateImages[tmpl.id] || 'https://images.unsplash.com/photo-1523438885200-e635ba2c371e?auto=format&fit=crop&w=600&q=80';

    const col = document.createElement('div');
    col.className = 'col-md-6 col-lg-4';
    col.innerHTML = `
      <div class="template-card">
        <div class="template-preview" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.6)), url('${bgImage}') center/cover;">
          <span class="badge ${catBadgeClass} template-category-badge">${tmpl.category}</span>
          <div class="template-preview-inner" style="color: white; text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">
            <p class="tmpl-label" style="opacity: 0.9;">Premium Template</p>
            <h3 class="tmpl-names" style="color: white;">${tmpl.id === 'tmpl-007' ? 'Miftah & Agus' : tmpl.name}</h3>
            <p class="tmpl-date"><i class="bi bi-star-fill text-warning"></i> ${tmpl.id === 'tmpl-007' ? '07 Juni 2026' : 'Best Choice'}</p>
          </div>
        </div>
        <div class="template-body">
          <h5 class="template-name">${tmpl.name}</h5>
          <p class="template-desc">${tmpl.description || ''}</p>
          <div class="template-features">${featureTags}</div>
          <div class="template-footer">
            <span class="template-price">${formatRupiah(tmpl.price)}</span>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary" onclick="viewTemplateDetail('${tmpl.id}')"><i class="bi bi-eye"></i> Detail</button>
              <button class="btn btn-sm btn-primary" onclick="orderTemplate('${tmpl.id}')"><i class="bi bi-cart-plus"></i> Pesan</button>
            </div>
          </div>
        </div>
      </div>
    `;
    grid.appendChild(col);
  });
}

function viewTemplateDetail(id) {
  const tmpl = state.templates.find(t => t.id === id);
  if (!tmpl) return;

  const features = tmpl.features ? tmpl.features.split(',') : [];
  const featureList = features.map(f => `<li><i class="bi bi-check-circle-fill text-success me-2"></i>${f.trim()}</li>`).join('');
  const demoSlug = (tmpl.id === 'tmpl-007') ? 'miftah-agus' : 'miftah-agus';

  const body = document.getElementById('template-detail-body');
  body.innerHTML = `
    <div class="row g-4">
      <div class="col-md-5">
        <div class="template-preview rounded-3" style="background: ${tmpl.gradient || 'var(--gradient-primary)'}; height: 300px; display: flex; align-items: center; justify-content: center;">
          <div class="template-preview-inner text-center text-white" style="text-shadow: 1px 1px 4px rgba(0,0,0,0.6);">
            <p class="tmpl-label mb-1" style="letter-spacing: 2px;">The Wedding of</p>
            <h3 class="tmpl-names fw-bold fs-2 mb-2" style="font-family: var(--font-serif);">${tmpl.id === 'tmpl-007' ? 'Miftah & Agus' : 'Andi & Sari'}</h3>
            <p class="tmpl-date mb-0"><i class="bi bi-calendar-heart text-warning"></i> ${tmpl.id === 'tmpl-007' ? '07 Juni 2026' : '15 Agustus 2026'}</p>
          </div>
        </div>
      </div>
      <div class="col-md-7">
        <span class="badge bg-primary-subtle text-primary mb-2">${escapeHtml(tmpl.category)}</span>
        <h3 class="fw-bold mb-2" style="font-family: var(--font-serif);">${escapeHtml(tmpl.name)}</h3>
        <p class="fs-3 fw-bold text-primary mb-3">${formatRupiah(tmpl.price)}</p>
        <p class="text-muted mb-3">${tmpl.description || ''}</p>
        <h6 class="fw-bold mb-2">Fitur Termasuk:</h6>
        <ul class="list-unstyled mb-4">${featureList}</ul>
        <a href="preview.html?slug=${demoSlug}" target="_blank" class="btn btn-outline-primary btn-lg w-100 mb-2">
          <i class="bi bi-eye-fill"></i> Lihat Demo Undangan (${tmpl.id === 'tmpl-007' ? 'Miftah & Agus' : 'Live Demo'})
        </a>
        <button class="btn btn-primary btn-lg w-100" onclick="orderTemplate('${tmpl.id}'); closeModal('templateDetailModal');">
          <i class="bi bi-cart-check"></i> Pesan Template Ini
        </button>
      </div>
    </div>
  `;
  openModal('templateDetailModal');
}

function orderTemplate(id) {
  if (!state.user) {
    showToast('Silakan login terlebih dahulu untuk memesan', 'warning');
    openModal('loginModal');
    return;
  }

  const t = state.templates.find(tmpl => tmpl.id === id);
  if (!t) return;

  document.getElementById('checkout-template-id').value = t.id;
  document.getElementById('checkout-total').value = t.price;
  document.getElementById('checkout-summary').innerHTML = `
    <div class="d-flex align-items-center gap-3">
      <div style="width:60px; height:60px; border-radius:8px; background:${t.gradient || 'var(--gradient-primary)'}"></div>
      <div>
        <h6 class="fw-bold mb-1">${escapeHtml(t.name)}</h6>
        <span class="badge bg-primary-subtle text-primary">${escapeHtml(t.category)}</span>
      </div>
    </div>
    <hr>
    <div class="d-flex justify-content-between fw-bold fs-5">
      <span>Total Bayar</span>
      <span class="text-primary">${formatRupiah(t.price)}</span>
    </div>
  `;

  closeModal('templateDetailModal');
  openModal('checkoutModal');
}

// ========================================
//  CHECKOUT / ORDERS
// ========================================

async function handleCheckout(e) {
  e.preventDefault();

  const templateId = document.getElementById('checkout-template-id').value;
  const total = parseInt(document.getElementById('checkout-total').value);

  const result = await apiCall('create_order', {
    user_id: state.user.id,
    template_id: templateId,
    total: total,
    payment_method: 'Midtrans'
  });

  if (result && result.success) {
    closeModal('checkoutModal');
    showToast(`Pesanan berhasil dibuat! Membuka halaman pembayaran...`, 'success');
    document.getElementById('checkout-form').reset();
    showSection('my-orders');
    
    // Automatically trigger Midtrans payment popup
    setTimeout(() => {
      payWithMidtrans(result.order_id);
    }, 1000);
  } else {
    showToast(result ? result.message : 'Gagal membuat pesanan', 'danger');
  }
}

// ========================================
//  MY ORDERS
// ========================================

async function loadMyOrders() {
  if (!state.user) return;

  const data = await apiCall(`get_orders&user_id=${state.user.id}`);
  if (data && Array.isArray(data)) {
    state.orders = data;
    renderMyOrders();
  }
}

function renderMyOrders() {
  const container = document.getElementById('my-orders-container');
  container.innerHTML = '';

  if (state.orders.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-bag"></i>
        <h5>Belum Ada Pesanan</h5>
        <p>Anda belum memesan template undangan apapun.</p>
        <button class="btn btn-primary mt-3" onclick="showSection('catalog')"><i class="bi bi-grid"></i> Lihat Katalog</button>
      </div>`;
    return;
  }

  state.orders.forEach(o => {
    const card = document.createElement('div');
    card.className = 'order-card mb-3';
    card.innerHTML = `
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <span class="badge bg-light text-dark border mb-2">Order ID: ${o.id}</span>
            <h5 class="fw-bold mb-1">${escapeHtml(o.template_name)}</h5>
            <p class="text-muted small mb-0">${new Date(o.created_at).toLocaleDateString('id-ID')}</p>
          </div>
          <h5 class="fw-bold text-primary">${formatRupiah(o.total)}</h5>
        </div>
        
        <div class="row g-3 mb-4">
          <div class="col-sm-6">
            <div class="p-3 bg-light rounded">
              <span class="d-block small text-muted mb-1">Status Pembayaran</span>
              ${getPaymentBadge(o.payment_status)}
            </div>
          </div>
          <div class="col-sm-6">
            <div class="p-3 bg-light rounded">
              <span class="d-block small text-muted mb-1">Status Pesanan</span>
              ${getOrderBadge(o.order_status)}
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          ${o.payment_status === 'Belum Bayar' ? 
            `<button class="btn btn-primary btn-sm fw-bold px-3" onclick="payWithMidtrans('${o.id}')">Bayar Sekarang</button>` : ''}
          ${o.order_status === 'Aktif' && !o.inv_id ? 
            `<button class="btn btn-success btn-sm fw-bold px-3" onclick="openCustomizeInvitation('${o.id}', '${o.template_id}')"><i class="bi bi-magic me-1"></i> Buat Undangan</button>` : ''}
          ${o.order_status === 'Aktif' && o.inv_id ? 
            `<button class="btn btn-outline-primary btn-sm fw-bold px-3" onclick="openCustomizeInvitation('${o.id}', '${o.template_id}')"><i class="bi bi-pencil me-1"></i> Edit Undangan</button>
             <a href="preview.html?slug=${escapeHtml(o.inv_slug)}" target="_blank" class="btn btn-outline-success btn-sm fw-bold px-3"><i class="bi bi-eye me-1"></i> Lihat Web</a>` : ''}
        </div>
      </div>
    `;
    container.appendChild(card);
  });
}

function getPaymentBadge(status) {
  const map = {
    'Belum Bayar': 'badge-payment-belum',
    'Menunggu Verifikasi': 'badge-payment-menunggu',
    'Terverifikasi': 'badge-payment-verified',
    'Ditolak': 'badge-payment-rejected'
  };
  return `<span class="badge rounded-pill ${map[status] || ''}">${status}</span>`;
}

function getOrderBadge(status) {
  const map = {
    'Pending': 'badge-order-pending',
    'Diproses': 'badge-order-process',
    'Aktif': 'badge-order-active',
    'Selesai': 'badge-order-done',
    'Dibatalkan': 'badge-order-cancel'
  };
  return `<span class="badge rounded-pill ${map[status] || ''}">${status}</span>`;
}

// ========================================
//  PAYMENT WITH MIDTRANS
// ========================================

async function payWithMidtrans(orderId) {
  try {
    const result = await apiCall('get_snap_token', { order_id: orderId });
    if (result && result.success) {
      if (result.is_simulated) {
        showToast(result.message || 'Pembayaran berhasil disimulasikan!', 'success');
        loadMyOrders();
        return;
      }
      if (result.token) {
        window.snap.pay(result.token, {
          onSuccess: async function(result){
            await apiCall('check_payment_status', { order_id: orderId });
            showToast('Pembayaran berhasil dikonfirmasi!', 'success');
            loadMyOrders();
          },
          onPending: async function(result){
            await apiCall('check_payment_status', { order_id: orderId });
            showToast('Menunggu pembayaran Anda!', 'warning');
            loadMyOrders();
          },
          onError: function(result){
            showToast('Pembayaran gagal!', 'danger');
            loadMyOrders();
          },
          onClose: function(){
            showToast('Anda menutup popup sebelum menyelesaikan pembayaran.', 'warning');
          }
        });
      }
    } else {
      showToast(result ? result.message : 'Gagal mendapatkan token pembayaran', 'danger');
    }
  } catch (error) {
    showToast('Terjadi kesalahan', 'danger');
  }
}

// ========================================
//  INVITATION CUSTOMIZATION
// ========================================

function openCustomizeInvitation(orderId, templateId) {
  document.getElementById('cust-order-id').value = orderId;
  document.getElementById('cust-template-id').value = templateId;
  document.getElementById('customize-form').reset();
  openModal('customizeModal');
}

async function handleSaveInvitation(e) {
  e.preventDefault();

  const data = {
    order_id: document.getElementById('cust-order-id').value,
    template_id: document.getElementById('cust-template-id').value,
    groom_name: document.getElementById('cust-groom').value,
    bride_name: document.getElementById('cust-bride').value,
    groom_father: document.getElementById('cust-groom-father').value,
    groom_mother: document.getElementById('cust-groom-mother').value,
    bride_father: document.getElementById('cust-bride-father').value,
    bride_mother: document.getElementById('cust-bride-mother').value,
    wedding_date: document.getElementById('cust-date').value,
    wedding_time: document.getElementById('cust-time').value,
    ceremony_location: document.getElementById('cust-ceremony').value,
    reception_location: document.getElementById('cust-reception').value,
    background_music: document.getElementById('cust-music').value,
    theme_color: document.getElementById('cust-color').value,
    love_story: document.getElementById('cust-lovestory').value,
    custom_message: document.getElementById('cust-message').value
  };

  const result = await apiCall('save_invitation', data);
  if (result && result.success) {
    closeModal('customizeModal');
    showToast('Undangan berhasil dibuat! Anda dapat melihatnya di menu "Undangan Saya".', 'success');
    showSection('my-invitations');
  } else {
    showToast(result ? result.message : 'Gagal menyimpan undangan', 'danger');
  }
}

// ========================================
//  MY INVITATIONS
// ========================================

async function loadMyInvitations() {
  if (!state.user) return;

  const data = await apiCall(`get_my_invitations&user_id=${state.user.id}`);
  if (data && Array.isArray(data)) {
    state.invitations = data;
    renderMyInvitations();
  }
}

function renderMyInvitations() {
  const container = document.getElementById('my-invitations-container');
  container.innerHTML = '';

  if (state.invitations.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-envelope-heart"></i>
        <h5>Belum Ada Undangan</h5>
        <p>Buat pesanan dan kustomisasi undangan Anda terlebih dahulu.</p>
        <button class="btn btn-primary mt-3" onclick="showSection('catalog')"><i class="bi bi-grid"></i> Lihat Katalog</button>
      </div>`;
    return;
  }

  state.invitations.forEach(i => {
    const card = document.createElement('div');
    card.className = 'invitation-card mb-3';
    card.innerHTML = `
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">${escapeHtml(i.groom_name)} & ${escapeHtml(i.bride_name)}</h5>
          <span class="badge ${i.is_active ? 'bg-success' : 'bg-secondary'}">${i.is_active ? 'Aktif' : 'Nonaktif'}</span>
        </div>
        <p class="text-muted small mb-3"><i class="bi bi-calendar-event me-2"></i> ${new Date(i.wedding_date).toLocaleDateString('id-ID')} | ${i.wedding_time}</p>
        <p class="text-muted small mb-4"><i class="bi bi-geo-alt me-2"></i> ${escapeHtml(i.ceremony_location)}</p>
        
        <div class="d-flex gap-2 border-top pt-3">
          <button class="btn btn-primary btn-sm px-3" onclick="openCustomizeInvitation('${i.order_id}', '${i.template_id}')"><i class="bi bi-pencil me-1"></i> Edit</button>
          <a href="preview.html?slug=${escapeHtml(i.slug)}" target="_blank" class="btn btn-outline-success btn-sm px-3"><i class="bi bi-eye me-1"></i> Lihat</a>
          <button class="btn btn-outline-info btn-sm px-3" onclick="viewRSVP(${i.id})"><i class="bi bi-chat-heart me-1"></i> Buku Tamu</button>
        </div>
      </div>
    `;
    container.appendChild(card);
  });
}

function copyLink(url) {
  const fullUrl = window.location.origin + '/' + url;
  navigator.clipboard.writeText(fullUrl).then(() => {
    showToast('Link undangan berhasil disalin!', 'success');
  }).catch(() => {
    showToast('Gagal menyalin link', 'danger');
  });
}

async function viewRSVP(invitationId) {
  const data = await apiCall(`get_rsvp&invitation_id=${invitationId}`);
  if (!data || !Array.isArray(data)) return;

  let content = '';
  if (data.length === 0) {
    content = '<p class="text-muted text-center">Belum ada tamu yang RSVP</p>';
  } else {
    const attending = data.filter(r => r.attendance === 'Hadir').length;
    const notAttending = data.filter(r => r.attendance === 'Tidak Hadir').length;
    const totalGuests = data.reduce((sum, r) => sum + (parseInt(r.guest_count) || 1), 0);

    content = `
      <div class="row g-2 mb-3">
        <div class="col-4 text-center"><div class="p-2 bg-success-subtle rounded"><strong>${attending}</strong><br><small>Hadir</small></div></div>
        <div class="col-4 text-center"><div class="p-2 bg-danger-subtle rounded"><strong>${notAttending}</strong><br><small>Tidak Hadir</small></div></div>
        <div class="col-4 text-center"><div class="p-2 bg-info-subtle rounded"><strong>${totalGuests}</strong><br><small>Total Tamu</small></div></div>
      </div>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Nama</th><th>Status</th><th>Jumlah</th><th>Pesan</th></tr></thead>
        <tbody>${data.map(r => `<tr><td>${escapeHtml(r.guest_name)}</td><td><span class="badge ${r.attendance === 'Hadir' ? 'bg-success' : 'bg-danger'}">${escapeHtml(r.attendance)}</span></td><td>${r.guest_count}</td><td class="small text-muted">${escapeHtml(r.message || '-')}</td></tr>`).join('')}</tbody>
      </table></div>
    `;
  }

  document.getElementById('template-detail-body').innerHTML = content;
  document.querySelector('#templateDetailModal .modal-title').textContent = 'Daftar RSVP Tamu';
  openModal('templateDetailModal');
}


// ========================================
//  UTILITIES
// ========================================

function lightenColor(hex, percent) {
  const num = parseInt(hex.replace('#', ''), 16);
  const r = Math.min(255, (num >> 16) + Math.round(2.55 * percent));
  const g = Math.min(255, ((num >> 8) & 0x00FF) + Math.round(2.55 * percent));
  const b = Math.min(255, (num & 0x0000FF) + Math.round(2.55 * percent));
  return `#${(1 << 24 | r << 16 | g << 8 | b).toString(16).slice(1)}`;
}

// ========================================
//  CONTACT FORM
// ========================================
document.addEventListener('DOMContentLoaded', () => {
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();
      showToast('Pesan Anda berhasil terkirim! Tim kami akan segera menghubungi Anda.', 'success');
      contactForm.reset();
    });
  }
});

// ========================================
//  INITIALIZATION
// ========================================
document.addEventListener('DOMContentLoaded', async () => {
  await checkSession();
  if (document.getElementById('section-home')) {
    await loadTemplates();
    showSection('home');
  }
});
