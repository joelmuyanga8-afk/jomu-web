(() => {
  const STORAGE_KEY = "jomu_cookie_consent_v1";
  const VERSION = 1;
  const MAX_AGE_SECONDS = 60 * 60 * 24 * 180;

  function linkTo(fileName) {
    return window.location.pathname.toLowerCase().includes("/php/") ? `../${fileName}` : fileName;
  }

  function secureCookieSuffix() {
    return window.location.protocol === "https:" ? "; Secure" : "";
  }

  function setConsentCookie(name, value) {
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${MAX_AGE_SECONDS}; Path=/; SameSite=Lax${secureCookieSuffix()}`;
  }

  function readStoredConsent() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || parsed.version !== VERSION) return null;
      return parsed;
    } catch (err) {
      return null;
    }
  }

  function publishConsent(consent) {
    const payload = {
      version: VERSION,
      ts: Date.now(),
      necessary: true,
      analytics: !!consent.analytics,
      marketing: !!consent.marketing
    };

    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    setConsentCookie("jomu_consent_necessary", "granted");
    setConsentCookie("jomu_consent_analytics", payload.analytics ? "granted" : "denied");
    setConsentCookie("jomu_consent_marketing", payload.marketing ? "granted" : "denied");
    setConsentCookie("jomu_cookie_consent", "set");

    window.JoMuCookieConsent = payload;
    window.dispatchEvent(new CustomEvent("jomu:cookie-consent-updated", { detail: payload }));
    return payload;
  }

  function injectStyles() {
    if (document.getElementById("jomu-cookie-consent-style")) return;
    const style = document.createElement("style");
    style.id = "jomu-cookie-consent-style";
    style.textContent = `
      .jomu-cookie-banner {
        position: fixed;
        left: 16px;
        right: 16px;
        bottom: 16px;
        z-index: 1300;
        background: #fff;
        border: 1px solid rgba(0,0,0,0.12);
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(0,0,0,0.18);
        padding: 14px;
        max-width: 760px;
        margin: 0 auto;
      }
      .jomu-cookie-title {
        margin: 0 0 6px 0;
        font-size: 1.2rem;
        color: rgb(241, 90, 36);
        font-weight: 700;
      }
      .jomu-cookie-text {
        margin: 0;
        color: #2f3a44;
        font-size: 0.92rem;
        line-height: 1.35;
      }
      .jomu-cookie-links {
        margin-top: 7px;
        font-size: 0.86rem;
      }
      .jomu-cookie-links a {
        color: rgb(0, 0, 255);
        text-decoration: none;
      }
      .jomu-cookie-actions {
        margin-top: 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .jomu-cookie-btn {
        border: 1px solid #c9d0d7;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 0.88rem;
        font-weight: 600;
        background: #fff;
        color: #1f2a36;
      }
      .jomu-cookie-btn-primary {
        background: rgb(241, 90, 36);
        border-color: rgb(241, 90, 36);
        color: #fff;
      }
      .jomu-cookie-btn-secondary {
        background: rgb(0, 0, 255);
        border-color: rgb(0, 0, 255);
        color: #fff;
      }
      .jomu-cookie-settings {
        position: fixed;
        right: 16px;
        bottom: 16px;
        z-index: 1200;
        border: 0;
        border-radius: 999px;
        padding: 10px 14px;
        background: #fff;
        box-shadow: 0 8px 22px rgba(0,0,0,0.2);
        color: rgb(0, 0, 255);
        font-size: 0.84rem;
        font-weight: 700;
      }
      .jomu-cookie-modal {
        position: fixed;
        inset: 0;
        z-index: 1400;
        background: rgba(0,0,0,0.45);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
      }
      .jomu-cookie-modal.open {
        display: flex;
      }
      .jomu-cookie-panel {
        width: 100%;
        max-width: 560px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 14px 28px rgba(0,0,0,0.22);
        padding: 16px;
      }
      .jomu-cookie-panel h3 {
        margin: 0 0 8px 0;
        color: rgb(241, 90, 36);
      }
      .jomu-cookie-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #e7ebef;
        border-radius: 10px;
        padding: 10px;
        margin-top: 10px;
        gap: 10px;
      }
      .jomu-cookie-row p {
        margin: 3px 0 0 0;
        font-size: 0.84rem;
        color: #4e5a67;
      }
      @media (max-width: 575.98px) {
        .jomu-cookie-banner {
          left: 8px;
          right: 8px;
          bottom: 8px;
          padding: 12px;
        }
        .jomu-cookie-actions .jomu-cookie-btn {
          width: 100%;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function createConsentUI(existingConsent) {
    const modal = document.createElement("div");
    modal.className = "jomu-cookie-modal";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.innerHTML = `
      <div class="jomu-cookie-panel">
        <h3>Cookie Preferences</h3>
        <p style="margin:0; color:#36424f; font-size:0.9rem;">Choose how JoMu uses cookies on your device. . Remember,
         you can customise your cookie settings at any time in your privacy cookie settings.</p>
        <div class="jomu-cookie-row">
          <div>
            <strong>Strictly Necessary</strong>
            <p>Required for security, sign-in, and core website functionality.</p>
          </div>
          <input type="checkbox" checked disabled>
        </div>
        <div class="jomu-cookie-row">
          <div>
            <strong>Analytics</strong>
            <p>Helps us improve website performance and user experience.</p>
          </div>
          <input id="jomuCookieAnalytics" type="checkbox">
        </div>
        <div class="jomu-cookie-row">
          <div>
            <strong>Marketing</strong>
            <p>Helps show relevant offers, promotions, and business recommendations.</p>
          </div>
          <input id="jomuCookieMarketing" type="checkbox">
        </div>
        <div class="jomu-cookie-actions">
          <button type="button" class="jomu-cookie-btn" id="jomuCookieCancel">Cancel</button>
          <button type="button" class="jomu-cookie-btn jomu-cookie-btn-secondary" id="jomuCookieSave">Save Preferences</button>
        </div>
      </div>
    `;

    const banner = document.createElement("section");
    banner.className = "jomu-cookie-banner";
    banner.setAttribute("aria-live", "polite");
    banner.innerHTML = `
      <h3 class="jomu-cookie-title">This website uses Cookies</h3>
      <p class="jomu-cookie-text">JoMu uses cookies to keep the platform secure, improve performance, and personalize your experience.</p>
      <div class="jomu-cookie-links">
        <a href="${linkTo("privacypolicy.html")}#cookie-policy-settings">Privacy & Cookie Settings</a>
      </div>
      <div class="jomu-cookie-actions">
        <button type="button" class="jomu-cookie-btn" id="jomuCookieManage">Manage Preferences</button>
        <button type="button" class="jomu-cookie-btn" id="jomuCookieReject">Reject Non-Essential</button>
        <button type="button" class="jomu-cookie-btn jomu-cookie-btn-primary" id="jomuCookieAccept">Accept All</button>
      </div>
    `;

    document.body.appendChild(banner);
    document.body.appendChild(modal);

    const analyticsInput = modal.querySelector("#jomuCookieAnalytics");
    const marketingInput = modal.querySelector("#jomuCookieMarketing");
    analyticsInput.checked = !!(existingConsent && existingConsent.analytics);
    marketingInput.checked = !!(existingConsent && existingConsent.marketing);

    function openModal() {
      modal.classList.add("open");
    }

    function closeModal() {
      modal.classList.remove("open");
    }

    function complete(consent) {
      publishConsent(consent);
      banner.style.display = "none";
      closeModal();
    }

    banner.querySelector("#jomuCookieAccept").addEventListener("click", () => {
      complete({ analytics: true, marketing: true });
    });

    banner.querySelector("#jomuCookieReject").addEventListener("click", () => {
      complete({ analytics: false, marketing: false });
    });

    banner.querySelector("#jomuCookieManage").addEventListener("click", openModal);
    document.querySelectorAll("[data-jomu-cookie-settings]").forEach((button) => {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        openModal();
      });
    });

    modal.querySelector("#jomuCookieCancel").addEventListener("click", closeModal);
    modal.querySelector("#jomuCookieSave").addEventListener("click", () => {
      complete({
        analytics: analyticsInput.checked,
        marketing: marketingInput.checked
      });
    });

    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });
    if (existingConsent) {
      banner.style.display = "none";
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    injectStyles();
    const consent = readStoredConsent();
    if (consent) {
      window.JoMuCookieConsent = consent;
      window.dispatchEvent(new CustomEvent("jomu:cookie-consent-updated", { detail: consent }));
    }
    createConsentUI(consent);
  });
})();
