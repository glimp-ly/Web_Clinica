(function () {
  'use strict';

  function isDarkStored() {
    try {
      if (localStorage.getItem('darkMode') === 'enabled') return true;
      if (localStorage.getItem('darkMode') === 'disabled') return false;
      if (localStorage.getItem('theme') === 'dark') return true;
      if (localStorage.getItem('theme') === 'light') return false;
      if (localStorage.getItem('theme-info') === 'dark') return true;
      if (localStorage.getItem('theme-info') === 'light') return false;
    } catch (e) {}
    return false;
  }

  function persistDark(isDark) {
    try {
      localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      localStorage.setItem('theme-info', isDark ? 'dark' : 'light');
    } catch (e) {}
  }

  function collectToggles() {
    var sels = ['#darkModeToggle', '#themeToggle', '#dark-mode-toggle'];
    var btns = [];
    sels.forEach(function (s) {
      document.querySelectorAll(s).forEach(function (b) {
        if (btns.indexOf(b) === -1) btns.push(b);
      });
    });
    document.querySelectorAll('.dark-mode-toggle, .theme-toggle, .dark-mode-switch').forEach(function (b) {
      if (btns.indexOf(b) === -1) btns.push(b);
    });
    return btns;
  }

  function ensureFloatingToggle() {
    var btn = document.getElementById('darkModeToggle');
    if (btn) return btn;
    var legacy = document.getElementById('themeToggle') || document.getElementById('dark-mode-toggle');
    if (legacy) {
      legacy.id = 'darkModeToggle';
      legacy.classList.add('dark-mode-toggle');
      return legacy;
    }
    btn = document.createElement('button');
    btn.id = 'darkModeToggle';
    btn.className = 'dark-mode-toggle';
    btn.setAttribute('aria-label', 'Cambiar modo oscuro/claro');
    btn.innerHTML = '<i class="fas fa-moon"></i>';
    document.body.appendChild(btn);
    return btn;
  }

  function paintIcons(isDark) {
    collectToggles().forEach(function (btn) {
      var icon = btn.querySelector('i');
      if (icon && (icon.classList.contains('fa-moon') || icon.classList.contains('fa-sun') ||
                   icon.classList.contains('fa-lightbulb') || icon.classList.contains('fa-circle-half-stroke'))) {
        icon.classList.remove('fa-moon', 'fa-sun');
        icon.classList.add(isDark ? 'fa-sun' : 'fa-moon');
      } else if (!icon) {
        var txt = (btn.textContent || '').trim();
        if (txt === '🌙' || txt === '☀️' || txt === '') {
          btn.innerHTML = '<i class="fas ' + (isDark ? 'fa-sun' : 'fa-moon') + '"></i>';
        }
      }
    });
  }

  function applyDark(isDark) {
    document.body.classList.toggle('dark-mode', isDark);
    document.body.classList.toggle('theme-dark', isDark);
    paintIcons(isDark);
  }

  function initTheme() {
    ensureFloatingToggle();
    applyDark(isDarkStored());
    collectToggles().forEach(function (btn) {
      if (btn.__layoutBound) return;
      btn.__layoutBound = true;
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var isDark = !(document.body.classList.contains('dark-mode') || document.body.classList.contains('theme-dark'));
        applyDark(isDark);
        persistDark(isDark);
      });
    });
  }

  function initMenu() {
    var toggle = document.querySelector('.menu-toggle');
    var nav = document.querySelector('.main-nav');
    var navLinks = document.querySelector('.nav-links');
    if (toggle && nav && !toggle.__layoutBound) {
      toggle.__layoutBound = true;
      toggle.addEventListener('click', function () {
        nav.classList.toggle('open');
        if (navLinks) navLinks.classList.toggle('active', nav.classList.contains('open'));
      });
    }
    if (navLinks && !navLinks.__layoutBound) {
      navLinks.__layoutBound = true;
      navLinks.addEventListener('click', function (e) {
        if (window.innerWidth <= 850) {
          var link = e.target.closest ? e.target.closest('a') : null;
          var li = e.target.closest ? e.target.closest('li') : null;
          if (link && li && li.querySelector('.submenu')) {
            e.preventDefault();
            var submenu = li.querySelector('.submenu');
            document.querySelectorAll('.nav-links > li.submenu-open').forEach(function (item) {
              if (item !== li) {
                item.classList.remove('submenu-open');
                var sm = item.querySelector('.submenu');
                if (sm) sm.style.maxHeight = '0px';
              }
            });
            li.classList.toggle('submenu-open');
            if (li.classList.contains('submenu-open')) {
              submenu.style.maxHeight = submenu.scrollHeight + 'px';
            } else {
              submenu.style.maxHeight = '0px';
            }
          }
        }
      });
    }
  }

  function initFooter() {
    var headers = document.querySelectorAll('.footer-links h4');
    headers.forEach(function (header) {
      if (header.__layoutBound) return;
      header.__layoutBound = true;
      header.addEventListener('click', function () {
        if (window.innerWidth > 767) return;
        var parent = header.closest('.footer-links');
        if (!parent) return;
        var content = parent.querySelector('.footer-content');
        if (!content) return;
        if (parent.classList.contains('active')) {
          content.style.maxHeight = '0px';
          parent.classList.remove('active');
        } else {
          document.querySelectorAll('.footer-links.active').forEach(function (item) {
            item.classList.remove('active');
            var c = item.querySelector('.footer-content');
            if (c) c.style.maxHeight = '0px';
          });
          parent.classList.add('active');
          content.style.maxHeight = content.scrollHeight + 'px';
        }
      });
    });
  }

  function initRevealAssist() {
    function revealInViewport() {
      var vh = window.innerHeight || document.documentElement.clientHeight;
      document.querySelectorAll('.reveal:not(.show)').forEach(function (el) {
        try {
          var r = el.getBoundingClientRect();
          if (r.top < vh * 0.92 && r.bottom > 0) el.classList.add('show');
        } catch (e) {}
      });
    }
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('show'); });
      return;
    }
    window.addEventListener('load', function () { setTimeout(revealInViewport, 600); });
    setTimeout(revealInViewport, 2500);
  }

  async function apiRequest(endpoint, options, maybeBody) {
    if (!endpoint.startsWith('api/') && !endpoint.startsWith('/api/')) {
      endpoint = 'api/' + endpoint.replace(/^\/+/, '');
    }
    if (typeof options === 'string') {
      options = { method: options, body: maybeBody };
    }
    options = options || {};
    var headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
    var config = {
      method: options.method || 'GET',
      headers: headers,
      credentials: 'same-origin'
    };
    if (options.body && typeof options.body !== 'string') {
      config.body = JSON.stringify(options.body);
    } else if (typeof options.body === 'string') {
      config.body = options.body;
    }
    var res = await fetch(endpoint, config);
    var data = {};
    try {
      data = await res.json();
    } catch (e) {}
    var ret = { status: res.status, ok: res.ok && data.ok !== false, data: data };
    if (data && typeof data === 'object') {
      for (var k in data) {
        if (!(k in ret)) {
          ret[k] = data[k];
        }
      }
    }
    return ret;
  }

  function showNotification(msg, type) {
    type = type || 'success';
    var existing = document.getElementById('globalClinicaToast');
    if (existing) existing.remove();
    var div = document.createElement('div');
    div.id = 'globalClinicaToast';
    div.className = 'global-toast global-toast-' + type;
    div.innerHTML = '<i class="fas ' + (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i> <span>' + msg + '</span>';
    document.body.appendChild(div);
    setTimeout(function () {
      if (div.parentNode) div.remove();
    }, 3500);
  }

  async function openGoogleAuthModal(filterRole, onSuccessRedirect) {
    var existing = document.getElementById('googleOAuthModal');
    if (existing) existing.remove();

    var res = await apiRequest('api/auth/google-accounts');
    var accounts = (res.ok && res.data.accounts) ? res.data.accounts : [];
    if (filterRole) {
      var filtered = accounts.filter(function (a) { return a.rol === filterRole; });
      if (filtered.length > 0) accounts = filtered.concat(accounts.filter(function (a) { return a.rol !== filterRole; }));
    }

    var roleLabels = {
      paciente: 'Paciente',
      medico: 'Médico',
      admin: 'Administrador'
    };

    var listHtml = accounts.map(function (acc) {
      var badgeClass = 'role-badge-' + acc.rol;
      var extra = acc.especialidad_nombre ? ' · ' + acc.especialidad_nombre : (acc.dni ? ' · DNI ' + acc.dni : '');
      return '<button type="button" class="google-acc-item" data-email="' + acc.email + '" data-name="' + acc.nombre + '" data-gid="' + (acc.google_id || '') + '">' +
        '<div class="google-acc-avatar">' + acc.nombre.charAt(0).toUpperCase() + '</div>' +
        '<div class="google-acc-info">' +
          '<div class="google-acc-name">' + acc.nombre + ' <span class="role-badge ' + badgeClass + '">' + (roleLabels[acc.rol] || acc.rol) + '</span></div>' +
          '<div class="google-acc-email">' + acc.email + extra + '</div>' +
        '</div>' +
      '</button>';
    }).join('');

    var overlay = document.createElement('div');
    overlay.id = 'googleOAuthModal';
    overlay.className = 'google-modal-overlay';
    overlay.innerHTML =
      '<div class="google-modal-card">' +
        '<div class="google-modal-header">' +
          '<div class="google-brand"><i class="fab fa-google" style="color:#0077b6;"></i> <span>Iniciar sesión con Google</span></div>' +
          '<button type="button" class="google-modal-close" aria-label="Cerrar">&times;</button>' +
        '</div>' +
        '<p class="google-modal-sub">Seleccione una cuenta de prueba (Paciente, Médico o Administrador) para continuar a <strong>Clínica Centenario del Norte</strong>:</p>' +
        '<div class="google-acc-list">' + listHtml + '</div>' +
        '<form id="googleCustomForm" class="google-custom-form">' +
          '<input type="email" id="googleCustomEmail" placeholder="O ingrese otro correo de Google..." required>' +
          '<button type="submit" class="btn-google-submit">Continuar</button>' +
        '</form>' +
      '</div>';

    document.body.appendChild(overlay);

    overlay.querySelector('.google-modal-close').addEventListener('click', function () {
      overlay.remove();
    });
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.remove();
    });

    async function doGoogleLogin(email, nombre, gid) {
      var r = await apiRequest('api/auth/google', {
        method: 'POST',
        body: { email: email, nombre: nombre, google_id: gid }
      });
      if (r.ok) {
        overlay.remove();
        showNotification('Autenticado con Google como ' + r.data.user.nombre, 'success');
        if (typeof onSuccessRedirect === 'function') {
          setTimeout(function () {
            onSuccessRedirect(r.data.user, r.data);
          }, 250);
        } else {
          var target = onSuccessRedirect || r.data.redirect || 'mis_citas.html';
          setTimeout(function () {
            window.location.href = target;
          }, 350);
        }
      } else {
        showNotification(r.data.message || 'Error al iniciar sesión con Google', 'error');
      }
    }

    overlay.querySelectorAll('.google-acc-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        doGoogleLogin(btn.getAttribute('data-email'), btn.getAttribute('data-name'), btn.getAttribute('data-gid'));
      });
    });

    overlay.querySelector('#googleCustomForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var em = overlay.querySelector('#googleCustomEmail').value.trim();
      if (em) {
        var namePart = em.split('@')[0].replace(/[._]/g, ' ');
        doGoogleLogin(em, namePart, 'google_custom_' + em);
      }
    });
  }

  async function syncAuthState() {
    var icon = document.getElementById('userIcon');
    var popup = document.getElementById('userPopup');
    var navActions = document.querySelector('.nav-actions');
    var headerNavLinks = document.querySelector('.header .nav-links');

    var res = await apiRequest('api/auth/me');
    var nameEl = popup ? popup.querySelector('.user-name') : null;
    var emailEl = popup ? popup.querySelector('.user-email') : null;
    var menuEl = popup ? popup.querySelector('.popup-menu') : null;
    var page = (window.location.pathname.split('/').pop() || 'index.html').toLowerCase();
    var patientSubPages = ['mis_citas.html', 'agendar_cita.html', 'farmacia.html', 'laboratorio.html', 'campa%c3%b1as.html', 'campañas.html', 'formulario_datos.html'];

    if (res.ok && res.data.user) {
      var u = res.data.user;
      window.__currentUser = u;

      if (navActions) {
        navActions.querySelectorAll('a[href="login.html"], a[href="register.html"]').forEach(function (a) {
          a.style.display = 'none';
        });
        if (!navActions.querySelector('.nav-role-shortcut')) {
          var shortcut = document.createElement('a');
          shortcut.className = 'btn btn-primary nav-role-shortcut';
          if (u.rol === 'admin') {
            shortcut.href = 'admin.html';
            shortcut.innerHTML = '<i class="fa-solid fa-sliders"></i>&nbsp; Panel Admin';
          } else if (u.rol === 'medico') {
            shortcut.href = 'medico.html';
            shortcut.innerHTML = '<i class="fa-solid fa-user-doctor"></i>&nbsp; Portal Médico';
          } else {
            shortcut.href = 'mis_citas.html';
            shortcut.innerHTML = '<i class="fa-regular fa-calendar-check"></i>&nbsp; Mi Portal';
          }
          navActions.insertBefore(shortcut, navActions.firstChild);
        }
      }

      if (headerNavLinks && patientSubPages.indexOf(page) !== -1 && u.rol === 'paciente') {
        var logoLink = document.querySelector('.header a.logo-link');
        if (logoLink) logoLink.setAttribute('href', 'mis_citas.html');
        var pLinks = [
          { href: 'mis_citas.html', label: 'Mis Citas y Lab' },
          { href: 'agendar_cita.html', label: 'Agendar Cita' },
          { href: 'farmacia.html', label: 'Farmacia' },
          { href: 'Laboratorio.html', label: 'Laboratorio' },
          { href: 'campañas.html', label: 'Campañas' },
          { href: 'formulario_datos.html', label: 'Mi Ficha Médica' }
        ];
        headerNavLinks.innerHTML = pLinks.map(function (item) {
          var isAct = page === item.href.toLowerCase() || (page === 'campa%c3%b1as.html' && item.href === 'campañas.html');
          return '<li><a href="' + item.href + '"' + (isAct ? ' class="role-nav-active"' : '') + '>' + item.label + '</a></li>';
        }).join('');
      }

      if (nameEl) nameEl.textContent = u.nombre + ' (' + u.rol.toUpperCase() + ')';
      if (emailEl) emailEl.textContent = u.email;

      if (menuEl) {
        var items = '';
        if (u.rol === 'admin') {
          items += '<li><a href="admin.html"><i class="fa-solid fa-gauge-high"></i> Panel Administrador</a></li>';
          items += '<li><a href="medico.html"><i class="fa-solid fa-user-doctor"></i> Vista Médico</a></li>';
          items += '<li><a href="index.html"><i class="fa-solid fa-house"></i> Landing Pública</a></li>';
        } else if (u.rol === 'medico') {
          items += '<li><a href="medico.html"><i class="fa-solid fa-stethoscope"></i> Mi Portal Médico</a></li>';
          items += '<li><a href="index.html"><i class="fa-solid fa-house"></i> Landing Pública</a></li>';
        } else {
          items += '<li><a href="mis_citas.html"><i class="fa-regular fa-calendar-check"></i> Mis Citas y Lab</a></li>';
          items += '<li><a href="agendar_cita.html"><i class="fa-solid fa-calendar-plus"></i> Agendar Cita</a></li>';
          items += '<li><a href="formulario_datos.html"><i class="fa-regular fa-id-card"></i> Mi Ficha Médica</a></li>';
          items += '<li><a href="index.html"><i class="fa-solid fa-house"></i> Inicio Público</a></li>';
        }
        items += '<li><a href="#" class="logout" id="logoutBtn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a></li>';
        menuEl.innerHTML = items;

        var logoutBtn = menuEl.querySelector('#logoutBtn');
        if (logoutBtn) {
          logoutBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            await apiRequest('api/auth/logout', { method: 'POST' });
            window.__currentUser = null;
            showNotification('Sesión cerrada correctamente', 'success');
            setTimeout(function () {
              window.location.href = 'index.html';
            }, 350);
          });
        }
      }
    } else {
      window.__currentUser = null;
      if (nameEl) nameEl.textContent = 'Invitado (Sin sesión)';
      if (emailEl) emailEl.textContent = 'Inicie sesión para acceder a su portal';
      if (menuEl) {
        menuEl.innerHTML =
          '<li><a href="login.html"><i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión</a></li>' +
          '<li><a href="register.html"><i class="fa-solid fa-user-plus"></i> Registrarse</a></li>' +
          '<li><a href="agendar_cita.html"><i class="fa-regular fa-calendar-plus"></i> Agendar Cita</a></li>' +
          '<li><a href="mis_citas.html"><i class="fa-regular fa-calendar-check"></i> Consultar Citas / Lab</a></li>';
      }
    }
  }

  function initUserPopup() {
    var icon = document.getElementById('userIcon');
    var popup = document.getElementById('userPopup');
    if (icon && popup && !icon.__layoutBound) {
      icon.__layoutBound = true;
      icon.addEventListener('click', function (e) {
        e.stopPropagation();
        popup.classList.toggle('active');
      });
      document.addEventListener('click', function (e) {
        if (!popup.classList.contains('active')) return;
        if (e.target === icon || icon.contains(e.target)) return;
        if (e.target === popup || popup.contains(e.target)) return;
        popup.classList.remove('active');
      });
    }
    syncAuthState();
  }

  window.ClinicaAPI = {
    request: apiRequest,
    notify: showNotification,
    toast: showNotification,
    openGoogleAuth: openGoogleAuthModal,
    openGoogleModal: openGoogleAuthModal,
    syncAuth: syncAuthState,
    getMe: async function () {
      var r = await apiRequest('api/auth/me');
      return (r.ok && r.data && r.data.user) ? r.data.user : null;
    },
    login: async function (email, password) {
      var r = await apiRequest('api/auth/login', 'POST', { email: email, password: password });
      if (!r.ok) {
        throw new Error(r.data.message || 'Error de autenticación');
      }
      syncAuthState();
      return r.data;
    },
    logout: async function () {
      await apiRequest('api/auth/logout', 'POST', {});
      syncAuthState();
    }
  };

  function initAll() {
    initTheme();
    initMenu();
    initFooter();
    initUserPopup();
    initRevealAssist();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
  document.addEventListener('DOMContentLoaded', initAll);
})();
