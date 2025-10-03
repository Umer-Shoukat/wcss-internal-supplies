// (function ($) {
//   // ---------- Tabs ----------

//   function switchTab(hash) {
//     $(".wcssm-tabs a").removeClass("active");
//     $('.wcssm-tabs a[href="' + hash + '"]').addClass("active");
//     $(".tab").removeClass("active");
//     $(hash).addClass("active");
//   }

//   $(function () {
//     const hash = window.location.hash || "#approvals";
//     switchTab(hash);
//     $(".wcssm-tabs a").on("click", function (e) {
//       e.preventDefault();
//       const h = this.getAttribute("href");
//       switchTab(h);
//       window.location.hash = h;
//     });
//   });

//   // ---------- Helpers ----------
//   function escapeHtml(s) {
//     return (s || "").replace(
//       /[&<>"']/g,
//       (m) =>
//         ({
//           "&": "&amp;",
//           "<": "&lt;",
//           ">": "&gt;",
//           '"': "&quot;",
//           "'": "&#39;",
//         }[m])
//     );
//   }
//   function objFromForm(form) {
//     const o = {};
//     new FormData(form).forEach((v, k) => (o[k] = v));
//     return o;
//   }
//   function get(endpoint, qp) {
//     return $.ajax({
//       url: WCSSM.rest + endpoint + (qp ? "?" + qp : ""),
//       method: "GET",
//       headers: { "X-WP-Nonce": WCSSM.nonce },
//     });
//   }
//   function post(path, data, method) {
//     return $.ajax({
//       url: WCSSM.rest + path,
//       method: method || "POST",
//       headers: {
//         "X-WP-Nonce": WCSSM.nonce,
//         "Content-Type": "application/json",
//       },
//       data: data ? JSON.stringify(data) : null,
//     });
//   }

//   // ---------- Generic list loader ----------
//   function fetchList($container, qp) {
//     const endpoint = $container.data("endpoint");
//     const presetQP = $container.data("filter") || "";
//     const finalQP = [presetQP, qp].filter(Boolean).join("&");
//     $container.html('<div class="card"><div>Loading…</div></div>');
//     get(endpoint, finalQP)
//       .done(function (payload) {
//         const rows = payload.items || payload;
//         if (!rows || !rows.length) {
//           $container.html('<div class="card"><div>No items.</div></div>');
//           return;
//         }
//         if (endpoint === "orders") return renderOrders($container, rows);
//         if (endpoint === "products") return renderProducts($container, rows);
//         if (endpoint === "stores") return renderStores($container, rows);
//         $container.html(
//           '<div class="card"><div>Unsupported endpoint</div></div>'
//         );
//       })
//       .fail(function (xhr) {
//         const msg = xhr.responseJSON?.message || xhr.statusText;
//         $container.html(
//           '<div class="card"><div>Error: ' + escapeHtml(msg) + "</div></div>"
//         );
//       });
//   }

//   // ---------- Orders ----------
//   function renderOrders($container, rows) {
//     const html = rows
//       .map(function (r) {
//         return (
//           '<div class="card" data-id="' +
//           r.id +
//           '">' +
//           "<div><strong>#" +
//           r.number +
//           "</strong> — " +
//           (r.customer || "") +
//           '<div class="meta">' +
//           r.date +
//           " • " +
//           r.status +
//           " • " +
//           r.total +
//           "</div>" +
//           "</div>" +
//           "<div>" +
//           '<select class="input order-status" data-id="' +
//           r.id +
//           '">' +
//           [
//             "pending",
//             "awaiting-approval",
//             "approved",
//             "rejected",
//             "processing",
//             "completed",
//             "cancelled",
//             "on-hold",
//           ]
//             .map(function (s) {
//               const sel = r.status_slug === s ? " selected" : "";
//               const label = s.replace(/(^|-)./g, (t) => t.toUpperCase());
//               return (
//                 '<option value="' + s + '"' + sel + ">" + label + "</option>"
//               );
//             })
//             .join("") +
//           "</select> " +
//           '<button class="btn act-order-status" data-id="' +
//           r.id +
//           '">Update</button> ' +
//           '<button class="btn" data-id="' +
//           r.id +
//           '" data-number="' +
//           r.number +
//           '" data-edd="' +
//           (r.edd || "") +
//           '" data-ref="' +
//           (r.ref || "") +
//           '" onclick="WCSSM_openOrderModal(this)">Edit</button>' +
//           "</div>" +
//           "</div>"
//         );
//       })
//       .join("");
//     $container.html(html);
//   }

//   $(document).on("click", ".act-order-status", function () {
//     const id = $(this).data("id");
//     const status = $(this).closest(".card").find(".order-status").val();
//     post("orders/" + id + "/status", { status })
//       .done(function () {
//         loadOrders();
//         // Also refresh approvals if on that tab
//         $('[data-endpoint="orders"][data-filter]').each(function () {
//           fetchList($(this));
//         });
//       })
//       .fail(function (xhr) {
//         alert(xhr.responseJSON?.message || "Update failed");
//       });
//   });

//   window.WCSSM_openOrderModal = function (btn) {
//     $("#order-modal").data("id", btn.getAttribute("data-id")).show();
//     $("#order-modal-title").text(
//       "Edit order #" + btn.getAttribute("data-number")
//     );
//     $("#om-edd").val(btn.getAttribute("data-edd") || "");
//     $("#om-ref").val(btn.getAttribute("data-ref") || "");
//     $("#om-note").val("");
//   };
//   $("#om-close").on("click", function () {
//     $("#order-modal").hide();
//   });
//   $("#om-save").on("click", function () {
//     const id = $("#order-modal").data("id");
//     const edd = $("#om-edd").val();
//     const ref = $("#om-ref").val();
//     const note = $("#om-note").val();
//     const ops = [];
//     if (edd || ref)
//       ops.push(
//         post(
//           "orders/" + id,
//           { expected_delivery_date: edd, internal_ref: ref },
//           "PATCH"
//         )
//       );
//     if (note) ops.push(post("orders/" + id + "/note", { note }, "POST"));
//     if (!ops.length) {
//       $("#order-modal").hide();
//       return;
//     }
//     Promise.all(ops)
//       .then(function () {
//         $("#order-modal").hide();
//         loadOrders();
//       })
//       .catch(function () {
//         alert("Save failed");
//       });
//   });

//   function loadOrders() {
//     const status = $("#orders-status-filter").val();
//     const qp = status ? "status=" + encodeURIComponent(status) : "";
//     fetchList($("#wcssm-orders-list"), qp);
//   }
//   $("#orders-refresh").on("click", loadOrders);
//   $("#orders-status-filter").on("change", loadOrders);

//   // ---------- Products ----------
//   function renderProducts($container, rows) {
//     const html = rows
//       .map(function (p) {
//         return (
//           '<div class="card" data-id="' +
//           p.id +
//           '">' +
//           "<div><strong>" +
//           escapeHtml(p.name) +
//           "</strong> • SKU: " +
//           (p.sku || "-") +
//           " • Price: " +
//           p.price +
//           " • " +
//           p.status +
//           "</div>" +
//           "<div>" +
//           '<button class="btn act-prod-edit" data-id="' +
//           p.id +
//           '">Edit</button> ' +
//           '<button class="btn btn-danger act-prod-del" data-id="' +
//           p.id +
//           '">Delete</button>' +
//           "</div>" +
//           "</div>"
//         );
//       })
//       .join("");
//     $container.html(html);
//   }

//   function resetProdForm() {
//     $("#form-product")[0].reset();
//     $("#form-product [name=id]").val("");
//     $("#prod-form-title").text("Create product");
//   }
//   $(document).on("submit", "#form-product", function (e) {
//     e.preventDefault();
//     const d = objFromForm(this);
//     const id = d.id;
//     delete d.id;
//     const req = id
//       ? post("products/" + id, d, "PUT")
//       : post("products", d, "POST");
//     req
//       .done(function () {
//         resetProdForm();
//         fetchList($("#wcssm-products-list"));
//       })
//       .fail(function (x) {
//         alert(x.responseJSON?.message || "Save failed");
//       });
//   });
//   $("#prod-cancel").on("click", resetProdForm);
//   $(document).on("click", ".act-prod-edit", function () {
//     const id = $(this).data("id");
//     get("products/" + id).done(function (p) {
//       $("#form-product [name=id]").val(p.id);
//       $("#prod-form-title").text("Edit product #" + p.id);
//       ["name", "sku", "price", "stock_qty", "status"].forEach((k) =>
//         $('#form-product [name="' + k + '"]').val(p[k] || "")
//       );
//     });
//   });
//   $(document).on("click", ".act-prod-del", function () {
//     if (!confirm("Delete this product?")) return;
//     const id = $(this).data("id");
//     post("products/" + id, null, "DELETE")
//       .done(function () {
//         fetchList($("#wcssm-products-list"));
//       })
//       .fail(function (x) {
//         alert(x.responseJSON?.message || "Delete failed");
//       });
//   });

//   // ---------- Stores ----------
//   function renderStores($container, rows) {
//     const html = rows
//       .map(function (s) {
//         return (
//           '<div class="card" data-id="' +
//           s.id +
//           '">' +
//           "<div><strong>" +
//           escapeHtml(s.name) +
//           "</strong> • Code: " +
//           (s.code || "-") +
//           " • City: " +
//           (s.city || "-") +
//           " • Quota: " +
//           (s.quota || 0) +
//           " • Budget: " +
//           (s.budget || 0) +
//           "</div>" +
//           "<div>" +
//           '<button class="btn act-store-edit" data-id="' +
//           s.id +
//           '">Edit</button> ' +
//           '<button class="btn btn-danger act-store-del" data-id="' +
//           s.id +
//           '">Delete</button>' +
//           "</div>" +
//           "</div>"
//         );
//       })
//       .join("");
//     $container.html(html);
//   }

//   function resetStoreForm() {
//     $("#form-store")[0].reset();
//     $("#form-store [name=id]").val("");
//     $("#store-form-title").text("Create store");
//   }
//   $(document).on("submit", "#form-store", function (e) {
//     e.preventDefault();
//     const d = objFromForm(this);
//     const id = d.id;
//     delete d.id;
//     const req = id ? post("stores/" + id, d, "PUT") : post("stores", d, "POST");
//     req
//       .done(function () {
//         resetStoreForm();
//         fetchList($("#wcssm-stores-list"));
//       })
//       .fail(function (x) {
//         alert(x.responseJSON?.message || "Save failed");
//       });
//   });
//   $("#store-cancel").on("click", resetStoreForm);
//   $(document).on("click", ".act-store-edit", function () {
//     const id = $(this).data("id");
//     get("stores/" + id).done(function (s) {
//       $("#form-store [name=id]").val(s.id);
//       $("#store-form-title").text("Edit store #" + s.id);
//       [
//         "name",
//         "code",
//         "address",
//         "city",
//         "state",
//         "postcode",
//         "country",
//         "quota",
//         "budget",
//       ].forEach((k) => $('#form-store [name="' + k + '"]').val(s[k] || ""));
//     });
//   });
//   $(document).on("click", ".act-store-del", function () {
//     if (!confirm("Delete this store?")) return;
//     const id = $(this).data("id");
//     post("stores/" + id, null, "DELETE")
//       .done(function () {
//         fetchList($("#wcssm-stores-list"));
//       })
//       .fail(function (x) {
//         alert(x.responseJSON?.message || "Delete failed");
//       });
//   });

//   // ---------- Boot lists ----------
//   $(function () {
//     $("[data-endpoint]").each(function () {
//       fetchList($(this));
//     });
//     if ($("#wcssm-orders-list").length) {
//       loadOrders();
//     }
//   });
// })(jQuery);

// // (function ($) {
// //   function priceFmt(currency, val) {
// //     // Simple client-side format
// //     try {
// //       return new Intl.NumberFormat(undefined, {
// //         style: "currency",
// //         currency,
// //       }).format(val);
// //     } catch (e) {
// //       return val.toFixed(2);
// //     }
// //   }

// //   // function loadStoresForPicker() {
// //   //   return $.ajax({
// //   //     url: WCSSM.rest + "stores",
// //   //     method: "GET",
// //   //     headers: { "X-WP-Nonce": WCSSM.nonce },
// //   //   }).then(function (payload) {
// //   //     const items = payload.items || [];
// //   //     const $sel = $("#wcssm-store-picker").empty();
// //   //     items.forEach(function (s) {
// //   //       $("<option>")
// //   //         .val(s.id)

// //   //         .appendTo($sel);
// //   //     });
// //   //     return items.length ? items[0].id : null;
// //   //   });
// //   // }

// //   function loadStoresForPicker() {
// //     return $.ajax({
// //       url: WCSSM.rest + "stores",
// //       method: "GET",
// //       headers: { "X-WP-Nonce": WCSSM.nonce },
// //     })
// //       .then(function (payload) {
// //         const items = payload && payload.items ? payload.items : [];
// //         const $sel = $("#wcssm-store-picker").empty();

// //         items.forEach(function (s) {
// //           const name = s.name || s.title || "Store #" + (s.id || "");
// //           const code = s.code ? " (" + s.code + ")" : "";
// //           $("<option>")
// //             .val(s.id)
// //             .text(name + code)
// //             .appendTo($sel);
// //         });

// //         return items.length ? items[0].id : null;
// //       })
// //       .catch(function () {
// //         $("#wcssm-store-picker").html('<option value="">No stores</option>');
// //         return null;
// //       });
// //   }

// //   function loadUsage() {
// //     const storeId = $("#wcssm-store-picker").val();
// //     const ym = $("#wcssm-month").val();
// //     if (!storeId || !ym) return;

// //     $.ajax({
// //       url:
// //         WCSSM.rest +
// //         "stores/" +
// //         storeId +
// //         "/ledger?month=" +
// //         encodeURIComponent(ym),
// //       method: "GET",
// //       headers: { "X-WP-Nonce": WCSSM.nonce },
// //     })
// //       .done(function (d) {
// //         // Orders card
// //         $("#k-orders").text(d.used_orders + " / " + (d.quota || 0));
// //         $("#k-orders-sub").text(
// //           "Remaining: " + Math.max(0, (d.quota || 0) - d.used_orders)
// //         );

// //         // Budget card
// //         $("#k-budget").text(
// //           priceFmt(d.currency, d.used_amount) +
// //             " / " +
// //             priceFmt(d.currency, d.budget || 0)
// //         );
// //         $("#k-budget-sub").text(
// //           "Remaining: " +
// //             priceFmt(d.currency, Math.max(0, (d.budget || 0) - d.used_amount))
// //         );

// //         // thresholds (80% warn, 100% bad)
// //         const pctOrders = d.quota > 0 ? d.used_orders / d.quota : 0;
// //         const pctBudget = d.budget > 0 ? d.used_amount / d.budget : 0;
// //         $("#k-orders")
// //           .toggleClass("kwarn", pctOrders >= 0.8 && pctOrders < 1)
// //           .toggleClass("kbad", pctOrders >= 1);
// //         $("#k-budget")
// //           .toggleClass("kwarn", pctBudget >= 0.8 && pctBudget < 1)
// //           .toggleClass("kbad", pctBudget >= 1);
// //       })
// //       .fail(function (xhr) {
// //         const msg = xhr.responseJSON?.message || xhr.statusText;
// //         $("#k-orders").text("—");
// //         $("#k-orders-sub").text("Error: " + msg);
// //         $("#k-budget").text("—");
// //         $("#k-budget-sub").text("");
// //       });
// //   }

// //   $("#wcssm-refresh-usage").on("click", loadUsage);
// //   $("#wcssm-store-picker,#wcssm-month").on("change", loadUsage);

// //   $(function () {
// //     // Populate store picker, then load usage
// //     loadStoresForPicker().then(function () {
// //       loadUsage();
// //     });
// //   });
// // })(jQuery);

// (function ($) {
//   function priceFmt(currency, val) {
//     try {
//       return new Intl.NumberFormat(undefined, {
//         style: "currency",
//         currency,
//       }).format(+val || 0);
//     } catch (e) {
//       return (+(val || 0)).toFixed(2);
//     }
//   }

//   function setCard($card, pct, warn, bad) {
//     $card.removeClass("kwarn kbad");
//     if (bad) $card.addClass("kbad");
//     else if (warn) $card.addClass("kwarn");

//     const $bar = $card.find(".bar");
//     $bar.css("width", Math.max(0, Math.min(100, Math.round(pct * 100))) + "%");
//   }

//   function yyyymmToPrevNext(val, dir) {
//     // val = "YYYY-MM"; dir = -1 or +1
//     const [y, m] = (val || "").split("-").map((n) => parseInt(n, 10));
//     if (!y || !m) return val;
//     const d = new Date(Date.UTC(y, m - 1 + dir, 1));
//     const yy = d.getUTCFullYear();
//     const mm = String(d.getUTCMonth() + 1).padStart(2, "0");
//     return `${yy}-${mm}`;
//   }

//   function loadStoresForPicker() {
//     return $.ajax({
//       url: WCSSM.rest + "stores",
//       method: "GET",
//       headers: { "X-WP-Nonce": WCSSM.nonce },
//     })
//       .then(function (payload) {
//         const items = payload && payload.items ? payload.items : [];
//         const $sel = $("#wcssm-store-picker").empty();
//         items.forEach(function (s) {
//           const name = s.name || s.title || "Store #" + (s.id || "");
//           const code = s.code ? " (" + s.code + ")" : "";
//           $("<option>")
//             .val(s.id)
//             .text(name + code)
//             .appendTo($sel);
//         });
//         return items.length ? items[0].id : null;
//       })
//       .catch(function () {
//         $("#wcssm-store-picker").html('<option value="">No stores</option>');
//         return null;
//       });
//   }

//   function showAlert(type, html) {
//     const $wrap = $("#wcssm-usage-alert").empty();
//     if (!html) {
//       $wrap.hide();
//       return;
//     }
//     const cls = type === "bad" ? "alert alert-bad" : "alert alert-warn";
//     $wrap.append('<div class="' + cls + '">' + html + "</div>").show();
//   }

//   function setLoading() {
//     $("#k-orders").text("Loading…");
//     $("#k-orders-sub").text("");
//     $("#k-budget").text("Loading…");
//     $("#k-budget-sub").text("");
//     $("#k-orders-bar, #k-budget-bar").css("width", "0%");
//     $("#k-orders-card, #k-budget-card").removeClass("kwarn kbad");
//     showAlert(null, "");
//   }

//   function loadUsage() {
//     const storeId = $("#wcssm-store-picker").val();
//     const ym = $("#wcssm-month").val();
//     if (!storeId || !ym) return;

//     setLoading();

//     $.ajax({
//       url:
//         WCSSM.rest +
//         "stores/" +
//         storeId +
//         "/ledger?month=" +
//         encodeURIComponent(ym),
//       method: "GET",
//       headers: { "X-WP-Nonce": WCSSM.nonce },
//     })
//       .done(function (d) {
//         const quota = parseInt(d.quota || 0, 10);
//         const usedO = parseInt(d.used_orders || 0, 10);
//         const budget = +d.budget || 0;
//         const usedA = +d.used_amount || 0;

//         // Orders card
//         $("#k-orders").text(usedO + " / " + quota);
//         $("#k-orders-sub").text("Remaining: " + Math.max(0, quota - usedO));
//         const pctO = quota > 0 ? usedO / quota : 0;
//         setCard($("#k-orders-card"), pctO, pctO >= 0.8 && pctO < 1, pctO >= 1);

//         // Budget card
//         $("#k-budget").text(
//           priceFmt(d.currency, usedA) + " / " + priceFmt(d.currency, budget)
//         );
//         $("#k-budget-sub").text(
//           "Remaining: " + priceFmt(d.currency, Math.max(0, budget - usedA))
//         );
//         const pctB = budget > 0 ? usedA / budget : 0;
//         setCard($("#k-budget-card"), pctB, pctB >= 0.8 && pctB < 1, pctB >= 1);

//         // Alerts
//         if (pctO >= 1 && pctB >= 1) {
//           showAlert(
//             "bad",
//             "Order count <strong>and</strong> budget limits reached for this month."
//           );
//         } else if (pctO >= 1) {
//           showAlert("bad", "Order count limit reached for this month.");
//         } else if (pctB >= 1) {
//           showAlert("bad", "Budget limit reached for this month.");
//         } else if (pctO >= 0.8 || pctB >= 0.8) {
//           showAlert(
//             "warn",
//             "Approaching monthly limit" +
//               (pctO >= 0.8 ? " (orders)" : "") +
//               (pctB >= 0.8 ? " (budget)" : "") +
//               "."
//           );
//         } else {
//           showAlert(null, "");
//         }
//       })
//       .fail(function (xhr) {
//         const msg =
//           xhr.responseJSON && xhr.responseJSON.message
//             ? xhr.responseJSON.message
//             : xhr.statusText || "Error";
//         $("#k-orders").text("—");
//         $("#k-orders-sub").text("");
//         $("#k-orders-card").removeClass("kwarn kbad");
//         $("#k-budget").text("—");
//         $("#k-budget-sub").text("");
//         $("#k-budget-card").removeClass("kwarn kbad");
//         showAlert("bad", "Failed to load usage: " + msg);
//       });
//   }

//   // Month navigation
//   $("#wcssm-month-prev").on("click", function () {
//     const cur = $("#wcssm-month").val();
//     $("#wcssm-month").val(yyyymmToPrevNext(cur, -1)).trigger("change");
//   });
//   $("#wcssm-month-next").on("click", function () {
//     const cur = $("#wcssm-month").val();
//     $("#wcssm-month").val(yyyymmToPrevNext(cur, +1)).trigger("change");
//   });

//   $("#wcssm-refresh-usage").on("click", loadUsage);
//   $("#wcssm-store-picker,#wcssm-month").on("change", loadUsage);

//   $(function () {
//     loadStoresForPicker().then(function () {
//       loadUsage();
//     });
//   });
// })(jQuery);

// // Load meta for product form (cats/brands/vendors lists)
// function loadProductMeta() {
//   return $.ajax({
//     url: WCSSM.rest + "products/meta",
//     method: "GET",
//     headers: { "X-WP-Nonce": WCSSM.nonce },
//   }).done(function (meta) {
//     // Categories
//     const $cats = $("#p-categories").empty();
//     (meta.categories || []).forEach(function (c) {
//       $("<option>").val(c.id).text(c.name).appendTo($cats);
//     });
//     // Optional: brand/vendor datalist (keep as free text)
//   });
// }

// // Image picker
// let mediaFrame = null;
// $("#p-pick-images").on("click", function (e) {
//   e.preventDefault();
//   if (!mediaFrame) {
//     mediaFrame = wp.media({
//       title: "Select product images",
//       button: { text: "Use these" },
//       multiple: true,
//     });
//     mediaFrame.on("select", function () {
//       const selection = mediaFrame.state().get("selection");
//       const ids = [];
//       $("#p-images-preview").empty();
//       selection.each(function (att) {
//         const id = att.get("id");
//         ids.push(id);
//         const url = att.get("sizes")?.thumbnail?.url || att.get("url");
//         $("#p-images-preview").append('<img src="' + url + '" alt="">');
//       });
//       $("#p-images-ids").val(ids.join(","));
//     });
//   }
//   mediaFrame.open();
// });

// // Create product
// $("#p-create").on("click", function () {
//   const catIds = ($("#p-categories").val() || []).map(function (v) {
//     return parseInt(v, 10);
//   });
//   const imageIds = ($("#p-images-ids").val() || "")
//     .split(",")
//     .map(function (v) {
//       return parseInt(v, 10) || null;
//     })
//     .filter(Boolean);

//   const payload = {
//     name: $("#p-name").val(),
//     sku: $("#p-sku").val(),
//     price: $("#p-price").val(),
//     short_description: $("#p-short").val(),
//     category_ids: catIds,
//     brand: $("#p-brand").val(),
//     vendor: $("#p-vendor").val(),
//     images: imageIds,
//     manage_stock: $("#p-manage-stock").is(":checked"),
//     stock: parseInt($("#p-stock").val() || 0, 10),
//     stock_status: $("#p-stock-status").val(),
//     status: $("#p-status").val(),
//   };

//   // Basic validation
//   if (!payload.name) {
//     $("#p-msg").text("Please enter a product name.");
//     return;
//   }
//   if (!payload.price) {
//     $("#p-msg").text("Please enter a price.");
//     return;
//   }

//   $("#p-create").prop("disabled", true);
//   $("#p-msg").text("Creating…");

//   $.ajax({
//     url: WCSSM.rest + "products",
//     method: "POST",
//     headers: { "X-WP-Nonce": WCSSM.nonce, "Content-Type": "application/json" },
//     data: JSON.stringify(payload),
//   })
//     .done(function (p) {
//       $("#p-msg").text("Created product #" + p.id + " — " + p.name);
//       $("#p-create").prop("disabled", false);
//       // TODO: refresh product list/grid
//     })
//     .fail(function (xhr) {
//       $("#p-msg").text(
//         "Error: " + (xhr.responseJSON?.message || xhr.statusText)
//       );
//       $("#p-create").prop("disabled", false);
//     });
// });

// Initialize form meta on tab load
// $(function () {
//   loadProductMeta();
// });

// (function ($) {
//   /* ---------- Shared helpers ---------- */

//   function priceFmt(currency, val) {
//     try {
//       return new Intl.NumberFormat(undefined, {
//         style: "currency",
//         currency,
//       }).format(+val || 0);
//     } catch (e) {
//       return (+(val || 0)).toFixed(2);
//     }
//   }

//   function loadProductMeta() {
//     return $.ajax({
//       url: WCSSM.rest + "products/meta",
//       method: "GET",
//       headers: { "X-WP-Nonce": WCSSM.nonce },
//     }).then(function (meta) {
//       const $cats = $("#p-categories").empty();
//       (meta.categories || []).forEach(function (c) {
//         $("<option>").val(c.id).text(c.name).appendTo($cats);
//       });
//       return meta;
//     });
//   }

//   function attachMediaPicker() {
//     let frame = null;
//     $("#p-pick-images")
//       .off("click")
//       .on("click", function (e) {
//         e.preventDefault();
//         if (!frame) {
//           frame = wp.media({
//             title: "Select product images",
//             button: { text: "Use these" },
//             multiple: true,
//           });
//           frame.on("select", function () {
//             const sel = frame.state().get("selection");
//             const ids = [];
//             $("#p-images-preview").empty();
//             sel.each(function (att) {
//               const id = att.get("id");
//               ids.push(id);
//               const url = att.get("sizes")?.thumbnail?.url || att.get("url");
//               $("#p-images-preview").append('<img src="' + url + '" alt="">');
//             });
//             $("#p-images-ids").val(ids.join(","));
//           });
//         }
//         frame.open();
//       });
//   }

//   function collectPayload() {
//     const catIds = ($("#p-categories").val() || []).map((v) => parseInt(v, 10));
//     const imageIds = ($("#p-images-ids").val() || "")
//       .split(",")
//       .map((v) => parseInt(v, 10) || null)
//       .filter(Boolean);
//     return {
//       name: $("#p-name").val(),
//       sku: $("#p-sku").val(),
//       price: $("#p-price").val(),
//       short_description: $("#p-short").val(),
//       category_ids: catIds,
//       brand: $("#p-brand").val(),
//       vendor: $("#p-vendor").val(),
//       images: imageIds,
//       manage_stock: $("#p-manage-stock").is(":checked"),
//       stock: parseInt($("#p-stock").val() || 0, 10),
//       stock_status: $("#p-stock-status").val(),
//       status: $("#p-status").val(),
//     };
//   }

//   function toggleStockInput() {
//     const on = $("#p-manage-stock").is(":checked");
//     $("#p-stock").prop("disabled", !on);
//   }

//   /* ---------- Page: Products List ---------- */
//   window.initProductsList = function () {
//     const $grid = $("#wcssm-products-grid");
//     function load() {
//       $grid.html("Loading…");
//       $.ajax({
//         url: WCSSM.rest + "products",
//         headers: { "X-WP-Nonce": WCSSM.nonce },
//       })
//         .done(function (d) {
//           const items = d.items || [];
//           if (!items.length) {
//             $grid.html("<p>No products yet.</p>");
//             return;
//           }

//           const rows = items
//             .map(
//               (p) => `
//           <div class="row">
//             <div>#${p.id}</div>
//             <div>${_.escape(p.name || "")}</div>
//             <div>${_.escape(p.sku || "")}</div>
//             <div>${_.escape(p.vendor || "")}</div>
//             <div>${p.price_html || p.price || ""}</div>
//             <div class="actions">
//               <a class="btn btn-sm" href="${WCSSM.home}products/edit/${
//                 p.id
//               }">Edit</a>
//               <button class="btn btn-sm btn-danger delete-product" data-id="${
//                 p.id
//               }">Delete</button>
//             </div>
//           </div>`
//             )
//             .join("");

//           $grid.html(`
//           <div class="row head">
//             <div>ID</div><div>Name</div><div>SKU</div><div>Vendor</div><div>Price</div><div>Actions</div>
//           </div>${rows}`);

//           $grid.find(".delete-product").on("click", function () {
//             const id = parseInt($(this).data("id"), 10);
//             if (!confirm("Delete product #" + id + "? This cannot be undone."))
//               return;
//             $.ajax({
//               url: WCSSM.rest + "products/" + id,
//               method: "DELETE",
//               headers: { "X-WP-Nonce": WCSSM.nonce },
//             })
//               .done(function () {
//                 load();
//               })
//               .fail(function (x) {
//                 alert("Error: " + (x.responseJSON?.message || x.statusText));
//               });
//           });
//         })
//         .fail(function (x) {
//           $grid.html("Error: " + (x.responseJSON?.message || x.statusText));
//         });
//     }
//     load();
//   };

//   /* ---------- Page: Product Create ---------- */
//   window.initProductCreate = function () {
//     attachMediaPicker();
//     $("#p-manage-stock").on("change", toggleStockInput);
//     toggleStockInput();

//     loadProductMeta().then(function () {
//       let busy = false;
//       $("#p-create").on("click", function () {
//         if (busy) return;
//         busy = true;
//         const payload = collectPayload();
//         if (!payload.name) {
//           $("#p-msg").text("Please enter product name.");
//           busy = false;
//           return;
//         }
//         if (!payload.price) {
//           $("#p-msg").text("Please enter product price.");
//           busy = false;
//           return;
//         }

//         $("#p-msg").text("Creating…");
//         $.ajax({
//           url: WCSSM.rest + "products",
//           method: "POST",
//           headers: {
//             "X-WP-Nonce": WCSSM.nonce,
//             "Content-Type": "application/json",
//           },
//           data: JSON.stringify(payload),
//         })
//           .done(function (p) {
//             $("#p-msg").text("Created #" + p.id + " — " + p.name);
//             // Optional redirect to edit:
//             // window.location = WCSSM.home + 'products/edit/' + p.id;
//           })
//           .fail(function (x) {
//             $("#p-msg").text(
//               "Error: " + (x.responseJSON?.message || x.statusText)
//             );
//           })
//           .always(function () {
//             busy = false;
//           });
//       });
//     });
//   };

//   /* ---------- Page: Product Edit ---------- */
//   window.initProductEdit = function (id) {
//     attachMediaPicker();
//     $("#p-manage-stock").on("change", toggleStockInput);

//     // Load meta & product data in parallel
//     $.when(
//       loadProductMeta(),
//       $.ajax({
//         url: WCSSM.rest + "products/" + id,
//         headers: { "X-WP-Nonce": WCSSM.nonce },
//       })
//     )
//       .done(function (metaResp, prodResp) {
//         const p = prodResp[0];

//         // Prefill
//         $("#p-name").val(p.name || "");
//         $("#p-sku").val(p.sku || "");
//         $("#p-price").val(p.price || "");
//         $("#p-short").val(p.short_description || "");
//         $("#p-brand").val(p.brand || "");
//         $("#p-vendor").val(p.vendor || "");

//         // Categories
//         const ids = p.category_ids || [];
//         $("#p-categories option").each(function () {
//           const v = parseInt($(this).val(), 10);
//           $(this).prop("selected", ids.includes(v));
//         });

//         // Images
//         if (Array.isArray(p.images) && p.images.length) {
//           $("#p-images-ids").val(p.images.join(","));
//           // We will fetch thumbs via a lightweight HEAD/URL? Not necessary: leave blank previews unless you want to resolve IDs to URLs.
//           // For a quick preview, you can skip or add an extra endpoint to resolve attachment URLs.
//         }

//         // Inventory
//         $("#p-manage-stock").prop("checked", !!p.manage_stock);
//         $("#p-stock").val(p.stock || 0);
//         $("#p-stock-status").val(p.stock_status || "instock");
//         toggleStockInput();

//         // Status
//         $("#p-status").val(p.status || "publish");

//         let busy = false;
//         $("#p-update").on("click", function () {
//           if (busy) return;
//           busy = true;
//           const payload = collectPayload();
//           $("#p-msg").text("Saving…");

//           $.ajax({
//             url: WCSSM.rest + "products/" + id,
//             method: "PUT",
//             headers: {
//               "X-WP-Nonce": WCSSM.nonce,
//               "Content-Type": "application/json",
//             },
//             data: JSON.stringify(payload),
//           })
//             .done(function (pp) {
//               $("#p-msg").text("Saved #" + pp.id + " — " + pp.name);
//             })
//             .fail(function (x) {
//               $("#p-msg").text(
//                 "Error: " + (x.responseJSON?.message || x.statusText)
//               );
//             })
//             .always(function () {
//               busy = false;
//             });
//         });
//       })
//       .fail(function (x) {
//         alert(
//           "Failed to load product: " + (x.responseJSON?.message || x.statusText)
//         );
//         window.location = WCSSM.home + "products";
//       });
//   };
// })(jQuery);

//  starting product CRUD JS

(function ($) {
  /* Helpers */
  function loadProductMeta() {
    return $.ajax({
      url: WCSSM.rest + "products/meta",
      headers: { "X-WP-Nonce": WCSSM.nonce },
    }).then(function (meta) {
      const $cats = $("#p-categories").empty();
      (meta.categories || []).forEach(function (c) {
        $("<option>").val(c.id).text(c.name).appendTo($cats);
      });
      return meta;
    });
  }

  function attachMediaPicker() {
    if (typeof wp === "undefined" || !wp.media) return;
    let frame = null;
    $("#p-pick-images")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        if (!frame) {
          frame = wp.media({
            title: "Select product images",
            button: { text: "Use these" },
            multiple: true,
          });
          frame.on("select", function () {
            const sel = frame.state().get("selection");
            const ids = [];
            $("#p-images-preview").empty();
            sel.each(function (att) {
              const id = att.get("id");
              ids.push(id);
              const url = att.get("sizes")?.thumbnail?.url || att.get("url");
              $("#p-images-preview").append('<img src="' + url + '" alt="">');
            });
            $("#p-images-ids").val(ids.join(","));
          });
        }
        frame.open();
      });
  }

  function collectPayload() {
    const catIds = ($("#p-categories").val() || []).map((v) => parseInt(v, 10));
    const vendorIds = ($("#p-vendors").val() || []).map((v) => parseInt(v, 10));
    const imageIds = ($("#p-images-ids").val() || "")
      .toString()
      .split(",")
      .map((v) => parseInt(v, 10) || null)
      .filter(Boolean);
    return {
      name: $("#p-name").val(),
      sku: $("#p-sku").val(),
      price: $("#p-price").val(),
      short_description: $("#p-short").val(),
      category_ids: catIds,
      brand: $("#p-brand").val(),
      vendor: $("#p-vendor").val(),
      vendor_ids: vendorIds,
      images: imageIds,
      manage_stock: $("#p-manage-stock").is(":checked"),
      stock: parseInt($("#p-stock").val() || 0, 10),
      stock_status: $("#p-stock-status").val(),
      status: $("#p-status").val(),
    };
  }

  function bindNewVendorButton() {
    $("#p-add-vendor")
      .off("click")
      .on("click", function () {
        const name = prompt("New vendor name:");
        if (!name) return;
        $.ajax({
          url: WCSSM.rest + "vendors",
          method: "POST",
          headers: {
            "X-WP-Nonce": WCSSM.nonce,
            "Content-Type": "application/json",
          },
          data: JSON.stringify({ name: name }),
        })
          .done(function (t) {
            // add & select
            $("#p-vendors").append(
              $("<option>").val(t.id).text(t.name).prop("selected", true)
            );
          })
          .fail(function (x) {
            alert(
              "Failed to create vendor: " +
                (x.responseJSON?.message || x.statusText)
            );
          });
      });
  }

  function toggleStockInput() {
    $("#p-stock").prop("disabled", !$("#p-manage-stock").is(":checked"));
  }

  /* List page */
  // window.initProductsList = function () {
  //   const $grid = $("#wcssm-products-grid");
  //   const $search = $("#p-search");

  //   function render(items) {
  //     if (!items.length) {
  //       $grid.html("<p>No products yet.</p>");
  //       return;
  //     }
  //     const rows = items
  //       .map(
  //         (p) => `
  //       <div class="row">
  //         <div>#${p.id}</div>
  //         <div>${(p.name || "").replace(/</g, "&lt;")}</div>
  //         <div>${(p.sku || "").replace(/</g, "&lt;")}</div>
  //         <div>${(p.vendor || "").replace(/</g, "&lt;")}</div>
  //         <div>${p.price_html || p.price || ""}</div>
  //         <div class="actions">
  //           <a class="btn btn-sm" href="${WCSSM.home}products/edit/${
  //           p.id
  //         }">Edit</a>
  //           <button class="btn btn-sm btn-danger delete-product" data-id="${
  //             p.id
  //           }">Delete</button>
  //         </div>
  //       </div>`
  //       )
  //       .join("");
  //     $grid.html(`
  //       <div class="row head">
  //         <div>ID</div><div>Name</div><div>SKU</div><div>Vendor</div><div>Price</div><div>Actions</div>
  //       </div>${rows}`);
  //     $grid.find(".delete-product").on("click", function () {
  //       const id = parseInt($(this).data("id"), 10);
  //       if (!confirm("Delete product #" + id + "?")) return;
  //       $.ajax({
  //         url: WCSSM.rest + "products/" + id,
  //         method: "DELETE",
  //         headers: { "X-WP-Nonce": WCSSM.nonce },
  //       })
  //         .done(load)
  //         .fail((x) =>
  //           alert("Error: " + (x.responseJSON?.message || x.statusText))
  //         );
  //     });
  //   }

  //   let cached = [];
  //   function load() {
  //     $grid.html("Loading…");
  //     $.ajax({
  //       url: WCSSM.rest + "products",
  //       headers: { "X-WP-Nonce": WCSSM.nonce },
  //     })
  //       .done((d) => {
  //         cached = d.items || [];
  //         applyFilter();
  //       })
  //       .fail((x) =>
  //         $grid.html("Error: " + (x.responseJSON?.message || x.statusText))
  //       );
  //   }

  //   function applyFilter() {
  //     const q = ($search.val() || "").toLowerCase();
  //     if (!q) return render(cached);
  //     render(
  //       cached.filter(
  //         (p) =>
  //           (p.name || "").toLowerCase().includes(q) ||
  //           (p.sku || "").toLowerCase().includes(q)
  //       )
  //     );
  //   }

  //   $("#p-refresh").on("click", load);
  //   $search.on("input", applyFilter);
  //   load();
  // };

  //update initiate product list

  window.initProductsList = function () {
    const $grid = $("#wcssm-products-grid");
    const $search = $("#p-search");
    const $refresh = $("#p-refresh");

    let state = { page: 1, per_page: 20, search: "" };
    /*
    function render(items, meta) {
      const hdr = `
        <div class="row head">
          <div>ID</div><div>Name</div><div>SKU</div><div>Vendor</div><div>Price</div><div>Actions</div>
        </div>`;
      const rows = items
        .map(
          (p) => `
        <div class="row">
          <div>#${p.id}</div>
          <div>${(p.name || "").replace(/</g, "&lt;")}</div>
          <div>${(p.sku || "").replace(/</g, "&lt;")}</div>
          <div>${(p.vendor || "").replace(/</g, "&lt;")}</div>
          <div>${p.price_html || p.price || ""}</div>
          <div class="actions">
            <a class="btn btn-sm" href="${WCSSM.home}products/edit/${
            p.id
          }">Edit</a>
            <button class="btn btn-sm btn-danger delete-product" data-id="${
              p.id
            }">Delete</button>
          </div>
        </div>`
        )
        .join("");

      const info = meta
        ? `
        <div class="pager-bar">
          <div class="pager-left">
            <button class="btn btn-light pager-prev" ${
              state.page <= 1 ? "disabled" : ""
            }>← Prev</button>
            <button class="btn btn-light pager-next" ${
              state.page >= meta.total_pages ? "disabled" : ""
            }>Next →</button>
          </div>
          <div class="pager-right">
            Page ${meta.page} / ${meta.total_pages} · ${meta.total} items
          </div>
        </div>`
        : "";

      $grid.html(`${hdr}${rows || "<p>No products.</p>"}${info}`);

      $grid.find(".pager-prev").on("click", function () {
        if (state.page > 1) {
          state.page--;
          load();
        }
      });
      $grid.find(".pager-next").on("click", function () {
        if (meta && state.page < meta.total_pages) {
          state.page++;
          load();
        }
      });

      $grid.find(".delete-product").on("click", function () {
        const id = parseInt($(this).data("id"), 10);
        if (!confirm("Delete product #" + id + "?")) return;
        $.ajax({
          url: WCSSM.rest + "products/" + id,
          method: "DELETE",
          headers: { "X-WP-Nonce": WCSSM.nonce },
        })
          .done(load)
          .fail((x) =>
            alert("Error: " + (x.responseJSON?.message || x.statusText))
          );
      });
    }

    function load() {
      $grid.html("Loading…");
      const qs = $.param({
        page: state.page,
        per_page: state.per_page,
        search: state.search,
      });
      $.ajax({
        url: WCSSM.rest + "products?" + qs,
        headers: { "X-WP-Nonce": WCSSM.nonce },
      })
        .done(function (d) {
          render(d.items || [], {
            total: d.total || 0,
            total_pages: d.total_pages || 1,
            page: d.page || 1,
          });
        })
        .fail(function (x) {
          $grid.html("Error: " + (x.responseJSON?.message || x.statusText));
        });
    }

*/

    function render(items, meta) {
      const head = `
      <div class="row head">
        <div>ID</div><div>Name</div><div>SKU</div><div>Vendor</div><div>Price</div><div>Actions</div>
      </div>`;
      const rows = (items || [])
        .map(
          (p) => `
      <div class="row">
        <div>#${p.id}</div>
        <div>${(p.name || "").replace(/</g, "&lt;")}</div>
        <div>${(p.sku || "").replace(/</g, "&lt;")}</div>
        <div>${(p.vendor || "").replace(/</g, "&lt;")}</div>
        <div>${p.price_html || p.price || ""}</div>
        <div class="actions">
          <a class="btn btn-sm" href="${WCSSM.home}products/edit/${
            p.id
          }">Edit</a>
          <button class="btn btn-sm btn-danger delete-product" data-id="${
            p.id
          }">Delete</button>
        </div>
      </div>`
        )
        .join("");

      const pager = `
      <div class="pager-bar">
        <button class="btn pager-prev" ${
          meta.page <= 1 ? "disabled" : ""
        }>← Prev</button>
        <span class="pager-info">Page ${meta.page} / ${meta.total_pages} · ${
        meta.total
      } items</span>
        <button class="btn pager-next" ${
          meta.page >= meta.total_pages ? "disabled" : ""
        }>Next →</button>
      </div>`;

      $grid.html(`${head}${rows || "<p>No products.</p>"}${pager}`);

      // pager events
      $grid.find(".pager-prev").on("click", function () {
        if (state.page > 1) {
          state.page--;
          load();
        }
      });
      $grid.find(".pager-next").on("click", function () {
        if (state.page < meta.total_pages) {
          state.page++;
          load();
        }
      });

      // delete
      $grid.find(".delete-product").on("click", function () {
        const id = parseInt($(this).data("id"), 10);
        if (!confirm("Delete product #" + id + "?")) return;
        $.ajax({
          url: WCSSM.rest + "products/" + id,
          method: "DELETE",
          headers: { "X-WP-Nonce": WCSSM.nonce },
        })
          .done(load)
          .fail((x) =>
            alert("Error: " + (x.responseJSON?.message || x.statusText))
          );
      });
    }

    function load() {
      $grid.html("Loading…");
      const qs = $.param({
        page: state.page,
        per_page: state.per_page,
        search: state.search,
      });
      $.ajax({
        url: WCSSM.rest + "products?" + qs,
        headers: { "X-WP-Nonce": WCSSM.nonce },
      })
        .done(function (d) {
          render(d.items || [], {
            total: d.total || 0,
            total_pages: d.total_pages || 1,
            page: d.page || state.page,
          });
        })
        .fail(function (x) {
          $grid.html("Error: " + (x.responseJSON?.message || x.statusText));
        });
    }

    // search hooks (optional)
    if ($refresh.length)
      $refresh.on("click", function () {
        state.page = 1;
        state.search = $search.val() || "";
        load();
      });
    if ($search.length)
      $search.on("keypress", function (e) {
        if (e.which === 13) {
          state.page = 1;
          state.search = $search.val() || "";
          load();
        }
      });

    load();
  };

  //   $refresh.on("click", function () {
  //     state.page = 1;
  //     state.search = $search.val() || "";
  //     load();
  //   });
  //   $search.on("keypress", function (e) {
  //     if (e.which === 13) {
  //       state.page = 1;
  //       state.search = $search.val() || "";
  //       load();
  //     }
  //   });

  //   load();
  // };

  /* Create page */

  function loadProductMeta() {
    return $.ajax({
      url: WCSSM.rest + "products/meta",
      headers: { "X-WP-Nonce": WCSSM.nonce },
    }).then(function (meta) {
      const $cats = $("#p-categories").empty();
      (meta.categories || []).forEach((c) =>
        $("<option>").val(c.id).html(c.name).appendTo($cats)
      );

      // ADD ↓
      const $vendors = $("#p-vendors").empty();
      (meta.vendors || []).forEach((v) =>
        $("<option>").val(v.id).text(v.name).appendTo($vendors)
      );
      // ADD ↑

      return meta;
    });
  }

  window.initProductCreate = function () {
    $("#create-actions").show();
    $("#p-manage-stock").on("change", toggleStockInput);
    toggleStockInput();
    attachMediaPicker();

    loadProductMeta().then(function () {
      let busy = false;
      $("#p-create").on("click", function () {
        if (busy) return;
        busy = true;
        const payload = collectPayload();
        if (!payload.name) {
          $("#p-msg").text("Please enter product name.");
          busy = false;
          return;
        }
        if (!payload.price) {
          $("#p-msg").text("Please enter product price.");
          busy = false;
          return;
        }

        $("#p-msg").text("Creating…");
        $.ajax({
          url: WCSSM.rest + "products",
          method: "POST",
          headers: {
            "X-WP-Nonce": WCSSM.nonce,
            "Content-Type": "application/json",
          },
          data: JSON.stringify(payload),
        })
          .done(function (p) {
            $("#p-msg").text("Created #" + p.id + " — " + p.name);
            window.location.href = "/manager/products";
            // Optional redirect:
            // window.location = WCSSM.home + "products/edit/" + p.id;
          })
          .fail(function (x) {
            $("#p-msg").text(
              "Error: " + (x.responseJSON?.message || x.statusText)
            );
          })
          .always(function () {
            busy = false;
          });
      });
    });
    bindNewVendorButton();
  };

  /* Edit page */
  window.initProductEdit = function (id) {
    $("#p-manage-stock").on("change", toggleStockInput);
    attachMediaPicker();

    $.when(
      loadProductMeta(),
      $.ajax({
        url: WCSSM.rest + "products/" + id,
        headers: { "X-WP-Nonce": WCSSM.nonce },
      })
    )
      .done(function (m, r) {
        const p = r[0];

        // Prefill
        $("#p-name").val(p.name || "");
        $("#p-sku").val(p.sku || "");
        $("#p-price").val(p.price || "");
        $("#p-short").val(p.short_description || "");
        $("#p-brand").val(p.brand || "");
        // $("#p-vendor").val(p.vendor || "");
        const vids = Array.isArray(p.vendor_ids) ? p.vendor_ids : [];
        $("#p-vendors option").each(function () {
          const v = parseInt($(this).val(), 10);
          $(this).prop("selected", vids.includes(v));
        });

        // Categories
        const ids = p.category_ids || [];
        $("#p-categories option").each(function () {
          const v = parseInt($(this).val(), 10);
          $(this).prop("selected", ids.includes(v));
        });

        // Images (store IDs; we won’t back-fill previews unless you add an attachment lookup)
        if (Array.isArray(p.images) && p.images.length) {
          $("#p-images-ids").val(p.images.join(","));
        }

        // Inventory
        $("#p-manage-stock").prop("checked", !!p.manage_stock);
        $("#p-stock").val(p.stock || 0);
        $("#p-stock-status").val(p.stock_status || "instock");
        toggleStockInput();

        // Status
        $("#p-status").val(p.status || "publish");

        // Save
        let busy = false;
        $("#p-update").on("click", function () {
          if (busy) return;
          busy = true;
          const payload = collectPayload();
          $("#p-msg").text("Saving…");
          $.ajax({
            url: WCSSM.rest + "products/" + id,
            method: "PUT",
            headers: {
              "X-WP-Nonce": WCSSM.nonce,
              "Content-Type": "application/json",
            },
            data: JSON.stringify(payload),
          })
            .done(function (pp) {
              $("#p-msg").text("Saved #" + pp.id + " — " + pp.name);
            })
            .fail(function (x) {
              $("#p-msg").text(
                "Error: " + (x.responseJSON?.message || x.statusText)
              );
            })
            .always(function () {
              busy = false;
            });
        });
      })
      .fail(function (x) {
        alert(
          "Failed to load product: " + (x.responseJSON?.message || x.statusText)
        );
        window.location = WCSSM.home + "products";
      });

    bindNewVendorButton();
  };
})(jQuery);

// pagination and search on product listing pages

// create product form js
