document.addEventListener('alpine:init', () => {
  Alpine.data('navigation', () => ({ open: false, toggle() { this.open = !this.open; }, close() { this.open = false; } }));
  Alpine.data('operations', () => ({ expanded: window.innerWidth > 800, toggle() { this.expanded = !this.expanded; } }));
});
document.querySelectorAll('[data-timeline-left]').forEach(element => {
  const left = Number(element.dataset.timelineLeft);
  const width = Number(element.dataset.timelineWidth);
  if (Number.isFinite(left) && Number.isFinite(width)) {
    element.style.left = Math.max(0, Math.min(100, left)) + '%';
    element.style.width = Math.max(0, Math.min(100 - left, width)) + '%';
  }
});
"use strict";
const money = (value) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 2,
  }).format(value);
document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
  const sync = () =>
    button.setAttribute(
      "aria-pressed",
      String(document.documentElement.dataset.theme === "dark"),
    );
  sync();
  button.addEventListener("click", () => {
    const theme =
      document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    document.documentElement.dataset.theme = theme;
    try {
      localStorage.setItem("glowbook-theme", theme);
    } catch {}
    sync();
  });
});
document.querySelectorAll("[data-booking-wizard]").forEach((form) => {
  let step = 0;
  let category = "all";
  const panels = [...form.querySelectorAll("[data-step]")];
  const selected = () => [
    ...form.querySelectorAll('[name="service_ids[]"]:checked'),
  ];
  const error = form.querySelector("[data-wizard-error]");
  const next = form.querySelector("[data-step-next]");
  const back = form.querySelector("[data-step-back]");
  const show = (index, focus = true) => {
    step = index;
    panels.forEach((panel, i) => {
      panel.hidden = i !== step;
    });
    form.querySelectorAll("[data-step-marker]").forEach((marker, i) => {
      if (i === step) marker.setAttribute("aria-current", "step");
      else marker.removeAttribute("aria-current");
    });
    back.hidden = step === 0;
    next.hidden = step === panels.length - 1;
    error.textContent = "";
    if (focus) panels[step].querySelector("h2").focus();
  };
  const summary = () => {
    const services = selected();
    const list = form.querySelector("[data-summary-services]");
    list.replaceChildren();
    services.forEach((input) => {
      const li = document.createElement("li");
      li.textContent = `${input.dataset.name} · ${money(Number(input.dataset.price))}`;
      list.append(li);
    });
    if (!services.length) {
      const li = document.createElement("li");
      li.textContent = "Chọn dịch vụ để bắt đầu.";
      list.append(li);
    }
    form.querySelector("[data-summary-total]").textContent = money(
      services.reduce(
        (sum, input) => sum + Math.round(Number(input.dataset.price) * 100),
        0,
      ) / 100,
    );
    form.querySelector("[data-mobile-total]").textContent = form.querySelector(
      "[data-summary-total]",
    ).textContent;
    form.querySelector("[data-summary-duration]").textContent = services.reduce(
      (sum, input) => sum + Number(input.dataset.duration),
      0,
    );
    form.querySelector("[data-review-time]").textContent =
      `Ngày ${form.elements.date.value} · ${form.elements.time.value}`;
    form.querySelector("[data-review-staff]").textContent =
      form.elements.staff_id.selectedOptions[0].textContent;
    form.querySelector("[data-review-voucher]").textContent = form.elements
      .voucher_code.value
      ? `Mã ưu đãi: ${form.elements.voucher_code.value} (chờ kiểm tra)`
      : "Không sử dụng mã ưu đãi.";
    form
      .querySelectorAll("[data-date-choice]")
      .forEach((button) =>
        button.setAttribute(
          "aria-pressed",
          String(button.dataset.dateChoice === form.elements.date.value),
        ),
      );
  };
  const validateStep = () => {
    if (step === 0 && !selected().length) {
      error.textContent = "Vui lòng chọn ít nhất một dịch vụ.";
      return false;
    }
    for (const input of panels[step].querySelectorAll(
      "input,select,textarea",
    )) {
      if (!input.checkValidity()) {
        input.reportValidity();
        return false;
      }
    }
    return true;
  };
  next.addEventListener("click", () => {
    if (validateStep()) {
      summary();
      show(step + 1);
    }
  });
  back.addEventListener("click", () => show(step - 1));
  form.addEventListener("change", summary);
  form.addEventListener("input", summary);
  form.querySelectorAll("[data-date-choice]").forEach((button) =>
    button.addEventListener("click", () => {
      form.elements.date.value = button.dataset.dateChoice;
      form.elements.date.dispatchEvent(new Event("change", { bubbles: true }));
    }),
  );
  const filter = () => {
    const query = form
      .querySelector("[data-service-search]")
      .value.toLocaleLowerCase("vi")
      .trim();
    let count = 0;
    form.querySelectorAll("[data-service-card]").forEach((card) => {
      card.hidden =
        !(category === "all" || card.dataset.categoryId === category) ||
        !card.textContent.toLocaleLowerCase("vi").includes(query);
      if (!card.hidden) count++;
    });
    form.querySelector("[data-service-empty]").hidden = count > 0;
  };
  form.querySelector("[data-service-search]").addEventListener("input", filter);
  form.querySelectorAll("[data-category]").forEach((button) =>
    button.addEventListener("click", () => {
      category = button.dataset.category;
      form
        .querySelectorAll("[data-category]")
        .forEach((pill) =>
          pill.setAttribute("aria-pressed", String(pill === button)),
        );
      filter();
    }),
  );
  form.addEventListener(
    "invalid",
    (event) => {
      const panel = event.target.closest("[data-step]");
      if (panel && panel.hidden) show(Number(panel.dataset.step), false);
    },
    true,
  );
  form.addEventListener("submit", (event) => {
    if (step !== panels.length - 1) {
      event.preventDefault();
      if (validateStep()) show(step + 1);
    } else if (!selected().length) {
      event.preventDefault();
      show(0);
      error.textContent = "Vui lòng chọn ít nhất một dịch vụ.";
    }
  });
  form.classList.add("wizard-ready");
  form.querySelector("[data-wizard-actions]").hidden = false;
  show(0, false);
  summary();
});
document.querySelectorAll("[data-submit-form]").forEach((form) => {
  const button = form.querySelector("[data-submit-button]");
  if (!button) return;
  const spinner = button.querySelector(".button-spinner");
  const status = form.querySelector("[data-submit-status]");
  const initiallyDisabled = button.disabled;
  form.addEventListener("submit", (event) => {
    if (event.defaultPrevented) return;
    button.disabled = true;
    if (spinner) spinner.hidden = false;
    form.setAttribute("aria-busy", "true");
    if (status) status.textContent = "Đang xử lý, vui lòng chờ…";
  });
  window.addEventListener("pageshow", () => {
    button.disabled = initiallyDisabled;
    form.removeAttribute("aria-busy");
    if (spinner) spinner.hidden = true;
    if (status) status.textContent = "";
  });
});
document.querySelectorAll("[data-availability-url]").forEach((form) => {
  const button = form.querySelector("[data-check-availability]");
  const status = form.querySelector("[data-availability-status]");
  let controller;
  let version = 0;
  const invalidate = (event) => {
    if (
      !["date", "time", "staff_id", "service_ids[]"].includes(event.target.name)
    )
      return;
    version++;
    controller?.abort();
    button.disabled = false;
    status.removeAttribute("aria-busy");
    delete status.dataset.state;
    status.textContent =
      "Lựa chọn đã thay đổi. Vui lòng kiểm tra lại khung giờ.";
  };
  form.addEventListener("change", invalidate);
  button.addEventListener("click", async () => {
    const input = new FormData(form);
    const query = new URLSearchParams();
    ["date", "time", "staff_id"].forEach((key) => {
      if (input.get(key)) query.set(key, input.get(key));
    });
    input
      .getAll("service_ids[]")
      .forEach((id) => query.append("service_ids[]", id));
    const current = ++version;
    controller?.abort();
    controller = new AbortController();
    button.disabled = true;
    status.setAttribute("aria-busy", "true");
    delete status.dataset.state;
    status.textContent = "Đang kiểm tra lịch của salon…";
    try {
      const response = await fetch(`${form.dataset.availabilityUrl}?${query}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      const body = await response.json();
      if (current !== version) return;
      status.dataset.state =
        response.ok && body.available ? "available" : "unavailable";
      status.textContent = response.ok
        ? `${body.message} Khung giờ chưa được giữ.`
        : Object.values(body.errors || {})
            .flat()
            .join(" ") || "Không thể kiểm tra lúc này. Vui lòng thử lại.";
    } catch (error) {
      if (error.name !== "AbortError" && current === version)
        status.textContent = "Không thể kết nối. Vui lòng thử lại.";
    } finally {
      if (current === version) {
        button.disabled = false;
        status.removeAttribute("aria-busy");
      }
    }
  });
});
document.querySelectorAll("[data-hold-until]").forEach((element) => {
  const deadline = Date.parse(element.dataset.holdUntil);
  if (!Number.isFinite(deadline)) return;
  let interval;
  const update = () => {
    const seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    element.textContent = seconds
      ? `Giữ chỗ còn ${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`
      : "Thời hạn giữ chỗ đã kết thúc. Vui lòng tải lại để cập nhật.";
    if (!seconds && interval) clearInterval(interval);
  };
  update();
  if (deadline > Date.now()) interval = setInterval(update, 1000);
});
document.querySelectorAll("[data-confirm]").forEach((form) =>
  form.addEventListener("submit", (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }),
);
document.querySelectorAll("[data-slots-url]").forEach((form) => {
  const load = form.querySelector("[data-load-slots]");
  const grid = form.querySelector("[data-slot-grid]");
  const status = form.querySelector("[data-slots-status]");
  let controller;
  let version = 0;
  form.addEventListener("change", (event) => {
    if (["date", "staff_id", "service_ids[]"].includes(event.target.name)) {
      version++;
      controller?.abort();
      load.disabled = false;
      grid.replaceChildren();
      grid.removeAttribute("aria-busy");
      status.textContent =
        "Lựa chọn đã thay đổi. Bấm xem giờ còn trống để cập nhật.";
    }
    if (event.target.name === "time")
      grid
        .querySelectorAll("[data-slot]")
        .forEach((button) =>
          button.setAttribute(
            "aria-pressed",
            String(button.dataset.slot === form.elements.time.value),
          ),
        );
  });
  load.addEventListener("click", async () => {
    const data = new FormData(form);
    const query = new URLSearchParams();
    ["date", "staff_id"].forEach((key) => {
      if (data.get(key)) query.set(key, data.get(key));
    });
    data
      .getAll("service_ids[]")
      .forEach((id) => query.append("service_ids[]", id));
    controller?.abort();
    controller = new AbortController();
    const current = ++version;
    load.disabled = true;
    grid.replaceChildren();
    grid.setAttribute("aria-busy", "true");
    status.textContent = "Đang tìm khung giờ phù hợp…";
    for (let i = 0; i < 8; i++) {
      const skeleton = document.createElement("span");
      skeleton.className = "slot-skeleton";
      skeleton.setAttribute("aria-hidden", "true");
      grid.append(skeleton);
    }
    try {
      const response = await fetch(`${form.dataset.slotsUrl}?${query}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      const body = await response.json();
      if (current !== version) return;
      grid.replaceChildren();
      if (!response.ok) {
        status.textContent =
          Object.values(body.errors || {})
            .flat()
            .join(" ") || "Không thể tải giờ hẹn. Vui lòng thử lại.";
        return;
      }
      status.textContent = body.message;
      body.slots.forEach((slot) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "slot-choice";
        button.disabled = !slot.available;
        button.dataset.slot = slot.time;
        button.textContent = `${slot.time} · ${slot.available ? "Còn chỗ" : "Không trống"}`;
        button.title = slot.message;
        button.setAttribute("aria-label", `${slot.time}: ${slot.message}`);
        button.setAttribute(
          "aria-pressed",
          String(slot.time === form.elements.time.value),
        );
        button.addEventListener("click", () => {
          form.elements.time.value = slot.time;
          form.elements.time.dispatchEvent(
            new Event("change", { bubbles: true }),
          );
        });
        grid.append(button);
      });
    } catch (error) {
      if (current === version && error.name !== "AbortError") {
        grid.replaceChildren();
        status.textContent = "Không thể kết nối. Vui lòng thử lại.";
      }
    } finally {
      if (current === version) {
        load.disabled = false;
        grid.removeAttribute("aria-busy");
      }
    }
  });
});
const rescheduleDialog = document.querySelector("[data-reschedule-dialog]");
if (rescheduleDialog) {
  const form = rescheduleDialog.querySelector("form");
  const open = (source, date, time, staff) => {
    form.action = source.dataset.rescheduleUrl;
    form.elements.original_start.value = source.dataset.originalStart;
    form.elements.original_staff_id.value = source.dataset.originalStaff;
    form.elements.date.value =
      date || source.dataset.originalStart.slice(0, 10);
    form.elements.time.value =
      time || source.dataset.originalStart.slice(11, 16);
    form.elements.staff_id.value = staff || source.dataset.originalStaff;
    rescheduleDialog.querySelector("[data-reschedule-code]").textContent =
      source.dataset.bookingCode;
    rescheduleDialog.showModal();
  };
  rescheduleDialog
    .querySelector("[data-reschedule-close]")
    .addEventListener("click", () => rescheduleDialog.close());
  document
    .querySelectorAll("[data-reschedule-open]")
    .forEach((button) => button.addEventListener("click", () => open(button)));
  let dragged;
  document.querySelectorAll("[data-reschedule-drag]").forEach((event) => {
    event.addEventListener("dragstart", (e) => {
      dragged = event;
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/plain", event.dataset.bookingCode);
    });
    event.addEventListener("dragend", () => {
      dragged = null;
      document
        .querySelectorAll(".drop-active")
        .forEach((track) => track.classList.remove("drop-active"));
    });
  });
  document.querySelectorAll("[data-drop-staff]").forEach((track) => {
    track.addEventListener("dragover", (e) => {
      if (dragged) {
        e.preventDefault();
        track.classList.add("drop-active");
        e.dataTransfer.dropEffect = "move";
      }
    });
    track.addEventListener("dragleave", () =>
      track.classList.remove("drop-active"),
    );
    track.addEventListener("drop", (e) => {
      e.preventDefault();
      track.classList.remove("drop-active");
      if (!dragged) return;
      const rect = track.getBoundingClientRect();
      const minutes = Math.max(
        0,
        Math.min(
          1425,
          Math.round((((e.clientX - rect.left) / rect.width) * 1440) / 15) * 15,
        ),
      );
      const time = `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;
      open(dragged, track.dataset.dropDate, time, track.dataset.dropStaff);
      dragged = null;
    });
  });
}

const summaryDialog = document.querySelector("[data-summary-dialog]");
if (summaryDialog) {
  document
    .querySelector("[data-open-summary]")
    .addEventListener("click", () => {
      const summary = document
        .querySelector(".booking-summary")
        .cloneNode(true);
      summary.classList.add("drawer-summary");
      summary.querySelector("h2")?.remove();
      summaryDialog
        .querySelector("[data-drawer-content]")
        .replaceChildren(summary);
      summaryDialog.showModal();
    });
  summaryDialog
    .querySelector("[data-close-summary]")
    .addEventListener("click", () => summaryDialog.close());
  let start;
  const handle = summaryDialog.querySelector("[data-drawer-handle]");
  handle.addEventListener(
    "touchstart",
    (event) => {
      start = event.touches[0].clientY;
    },
    { passive: true },
  );
  handle.addEventListener(
    "touchend",
    (event) => {
      if (start !== undefined && event.changedTouches[0].clientY - start > 60)
        summaryDialog.close();
      start = undefined;
    },
    { passive: true },
  );
}
