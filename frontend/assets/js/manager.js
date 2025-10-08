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
  //update initiate product list

  window.initProductsList = function () {
    const $grid = $("#wcssm-products-grid");
    const $search = $("#p-search");
    const $refresh = $("#p-refresh");

    let state = { page: 1, per_page: 20, search: "" };

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
          <a class="btn btn-sm" href="edit/${p.id}">Edit</a>
          <button class="btn btn-sm btn-danger delete-product" data-id="${
            p.id
          }">Delete</button>
        </div>
      </div>`
        )
        .join("");

      const pager = `
        <div class="pager-bar">
          <button type="button" class="btn pager-prev" ${
            state.page <= 1 ? "disabled" : ""
          }>← Prev</button>
          <span class="pager-info">Page ${state.page} / ${
        state.total_pages
      } · ${state.total} items</span>
          <button type="button" class="btn pager-next" ${
            state.page >= state.total_pages ? "disabled" : ""
          }>Next →</button>
        </div>`;

      $grid.html(`${head}${rows || "<p>No products.</p>"}${pager}`);
      console.log($grid);
      const preBtn = $grid.find(".pager-prev");
      console.log(preBtn);

      // pager events
      $grid.find(".pager-prev").on("click", function () {
        console.log("btn clicked ....  ");
        if (state.page > 1) {
          state.page--;
          load();
        }
      });

      $grid.find(".pager-next").on("click", function () {
        console.log("btn clicked ....  ");
        if (state.page < meta.total_pages) {
          state.page++;
          load();
        }
      });

      // delete
      // $grid.find(".delete-product").on("click", function () {
      //   const id = parseInt($(this).data("id"), 10);
      //   if (!confirm("Delete product #" + id + "?")) return;
      //   $.ajax({
      //     url: WCSSM.rest + "products/" + id,
      //     method: "DELETE",
      //     headers: { "X-WP-Nonce": WCSSM.nonce },
      //   })
      //     .done(load)
      //     .fail((x) =>
      //       alert("Error: " + (x.responseJSON?.message || x.statusText))
      //     );
      // });

      // --- add near top of initProductsList() ---

      function flash(msg, ok = true) {
        const $f = $("#wcssm-flash");
        $f.removeClass("is-ok is-err")
          .addClass(ok ? "is-ok" : "is-err")
          .text(msg)
          .stop(true, true)
          .fadeIn(120);
        setTimeout(() => $f.fadeOut(180), 2500);
      }

      // --- replace your existing delete handler with this ---
      $grid.on("click", ".delete-product", function () {
        const id = parseInt($(this).data("id"), 10);
        if (!Number.isInteger(id)) return;

        if (!confirm("Delete product #" + id + "?")) return;

        $.ajax({
          url: WCSSM.rest + "products/" + id,
          method: "DELETE",
          headers: { "X-WP-Nonce": WCSSM.nonce },
          dataType: "json", // ensure .done() path on 200 JSON
        })
          .done(function () {
            flash("Product deleted.", true);
            load(); // reload list
          })
          .fail(function (x) {
            const msg =
              x.responseJSON && x.responseJSON.message
                ? x.responseJSON.message
                : x.statusText || "Unknown error";
            flash("Delete failed: " + msg, false);
          });
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
          // update state with server meta
          state.total = d.total || 0;
          state.total_pages = d.total_pages || 1;
          state.page = d.page || state.page;

          render(d.items || [], state); // pass state if you like, but render uses state anyway
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

  // create product form js
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
    const $msg = $("#p-msg");

    function toast(t, ok = true) {
      $msg
        .removeClass("ok err")
        .addClass(ok ? "ok" : "err")
        .text(t)
        .show();
    }

    function fetchProduct() {
      return $.ajax({
        url: WCSSM.rest + "products/" + id,
        headers: { "X-WP-Nonce": WCSSM.nonce },
      });
    }

    function prefillForm(p) {
      $("#p-name").val(p.name || "");
      $("#p-sku").val(p.sku || "");
      $("#p-price").val(p.price || "");
      $("#p-short").val(p.short_description || "");
      $("#p-status").val(p.status || "publish");

      $("#p-manage-stock").prop("checked", !!p.manage_stock);
      $("#p-stock").val(p.stock || 0);
      $("#p-stock-status").val(p.stock_status || "instock");

      // Preselect categories
      if (Array.isArray(p.category_ids)) {
        $("#p-categories option").each(function () {
          const v = parseInt($(this).val(), 10);
          $(this).prop("selected", p.category_ids.includes(v));
        });
      }

      // Preselect vendors
      if (Array.isArray(p.vendor_ids)) {
        $("#p-vendors option").each(function () {
          const v = parseInt($(this).val(), 10);
          $(this).prop("selected", p.vendor_ids.includes(v));
        });
      }

      // Images: set hidden field + render thumbs via wp.media (to get URLs)
      const ids = Array.isArray(p.images)
        ? p.images.map(Number).filter(Boolean)
        : [];
      $("#p-images-ids").val(ids.join(","));
      $("#p-images-preview").empty();

      // Fetch each attachment for preview
      if (window.wp && wp.media && ids.length) {
        ids.forEach(function (attId) {
          const att = wp.media.attachment(attId);
          att.fetch().then(function () {
            const url = att.get("url");
            if (url) $("#p-images-preview").append('<img src="' + url + '" />');
          });
        });
      }
    }

    function load() {
      // 1) Load meta (cats/vendors), 2) fetch product, then prefill
      $.when(loadProductMeta(), fetchProduct())
        .done(function (_, prodRes) {
          const p = (Array.isArray(prodRes) ? prodRes[0] : prodRes) || prodRes;
          prefillForm(p);
        })
        .fail(function (x) {
          toast(
            "Failed to load product: " +
              (x.responseJSON?.message || x.statusText),
            false
          );
        });
    }

    // Save handler (PUT)
    $("#p-save")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        toast("Saving…", true);
        const payload = collectPayload(); // reuses your existing function

        $.ajax({
          url: WCSSM.rest + "products/" + id,
          method: "PUT",
          headers: {
            "X-WP-Nonce": WCSSM.nonce,
            "Content-Type": "application/json",
          },
          data: JSON.stringify(payload),
        })
          .done(function () {
            toast("✅ Saved!", true);
            setTimeout(function () {
              window.location = (WCSSM.home || "/manager/") + "products";
            }, 900);
          })
          .fail(function (x) {
            toast("Error: " + (x.responseJSON?.message || x.statusText), false);
          });
      });

    // Image picker (same binding as create)
    $("#p-pick-images")
      .off("click")
      .on("click", function () {
        const frame = wp.media({
          title: "Select product images",
          multiple: true,
          library: { type: "image" },
        });
        frame.on("select", function () {
          const ids = [];
          const thumbs = [];
          frame
            .state()
            .get("selection")
            .each(function (att) {
              ids.push(att.get("id"));
              thumbs.push('<img src="' + att.get("url") + '" />');
            });
          $("#p-images-ids").val(ids.join(","));
          $("#p-images-preview").html(thumbs.join(""));
        });
        frame.open();
      });

    load();
  };
})(jQuery);

/* store curd below */

(function ($) {
  window.initStoresList = function () {
    const $grid = $("#wcssm-stores-grid");
    const $search = $("#s-search"),
      $refresh = $("#s-refresh");
    let state = { page: 1, per_page: 20, total_pages: 1, total: 0, search: "" };

    function escapeHtml(s) {
      return (s || "").replace(
        /[&<>"]/g,
        (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c])
      );
    }

    function render(items) {
      const head = `
      <div class="row head">
        <div>ID</div><div>Name</div><div>Code</div><div>City</div><div>State</div><div>Quota</div><div>Budget</div><div>Actions</div>
      </div>`;
      const rows = (items || [])
        .map(
          (s) => `
      <div class="row">
        <div>#${s.id}</div>
        <div>${escapeHtml(s.name || "")}</div>
        <div>${escapeHtml(s.code || "")}</div>
        <div>${escapeHtml(s.city || "")}</div>
        <div>${escapeHtml(s.state || "")}</div>
        <div>${s.quota ?? 0}</div>
        <div>${(s.budget ?? 0).toLocaleString()}</div>
        <div>
          <a class="btn btn-sm" href="${WCSSM.home}stores/edit/${s.id}">Edit</a>
          <button class="btn btn-sm btn-danger st-del" data-id="${
            s.id
          }">Delete</button>
        </div>
      </div>`
        )
        .join("");

      const pager = `
      <div class="pager-bar">
        <button type="button" class="btn pager-prev" ${
          state.page <= 1 ? "disabled" : ""
        }>← Prev</button>
        <span class="pager-info">Page ${state.page} / ${state.total_pages} · ${
        state.total
      } items</span>
        <button type="button" class="btn pager-next" ${
          state.page >= state.total_pages ? "disabled" : ""
        }>Next →</button>
      </div>`;

      $grid.html(head + rows + pager);
    }

    function load() {
      console.log("here");
      $grid.html("Loading…");
      const qs = $.param({
        page: state.page,
        per_page: state.per_page,
        search: state.search,
      });
      $.ajax({
        url: WCSSM.rest + "stores?" + qs,
        headers: { "X-WP-Nonce": WCSSM.nonce },
      })
        .done(function (d) {
          state.total = d.total || 0;
          state.total_pages = d.total_pages || 1;
          render(d.items || []);
        })
        .fail(function (x) {
          $grid.html("Error: " + (x.responseJSON?.message || x.statusText));
        });
    }

    // delegated so it survives re-renders
    $grid.on("click", ".pager-prev", function (e) {
      e.preventDefault();
      if (state.page > 1) {
        state.page--;
        load();
      }
    });
    $grid.on("click", ".pager-next", function (e) {
      e.preventDefault();
      if (state.page < state.total_pages) {
        state.page++;
        load();
      }
    });

    // $grid.on("click", ".st-del", function () {
    //   const id = parseInt($(this).data("id"), 10);
    //   if (!confirm("Delete store #" + id + "?")) return;
    //   $.ajax({
    //     url: WCSSM.rest + "stores/" + id,
    //     method: "DELETE",
    //     headers: { "X-WP-Nonce": WCSSM.nonce },
    //   })
    //     .done(load)
    //     .fail((x) =>
    //       alert("Error: " + (x.responseJSON?.message || x.statusText))
    //     );
    // });

    // helper for inline messages
    function flash(msg, ok = true) {
      const $f = $("#wcssm-flash");
      $f.removeClass("is-ok is-err")
        .addClass(ok ? "is-ok" : "is-err")
        .text(msg)
        .stop(true, true)
        .fadeIn(120);

      // auto-hide after 2.5s
      setTimeout(() => $f.fadeOut(180), 2500);
    }

    $grid.on("click", ".st-del", function () {
      const id = parseInt($(this).data("id"), 10);
      if (!Number.isInteger(id)) return;

      if (!confirm("Delete store #" + id + "?")) return;

      $.ajax({
        url: WCSSM.rest + "stores/" + id,
        method: "DELETE",
        headers: { "X-WP-Nonce": WCSSM.nonce },
        dataType: "json", // <- make jQuery treat it as JSON
      })
        .done(function () {
          flash("Store deleted.", true);
          load();
        })
        .fail(function (x) {
          const msg =
            x.responseJSON && x.responseJSON.message
              ? x.responseJSON.message
              : x.statusText || "Unknown error";
          flash("Delete failed: " + msg, false);
        });
    });

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

  // -------------------------------------
  // STORES – Create
  // -------------------------------------
  window.initStoreCreate = function () {
    const $msg = $("#s-msg");
    function val(id) {
      return $("#" + id).val();
    }
    function toast(t, ok = true) {
      $msg
        .removeClass("err ok")
        .addClass(ok ? "ok" : "err")
        .text(t)
        .show();
    }

    $("#s-save")
      .off("click")
      .on("click", function () {
        const payload = {
          name: val("s-name"),
          code: val("s-code"),
          city: val("s-city"),
          state: val("s-state"),
          quota: val("s-quota"),
          budget: val("s-budget"),
        };
        if (!payload.name) return toast("Name is required", false);

        toast("Saving…");
        $.ajax({
          url: WCSSM.rest + "stores",
          method: "POST",
          headers: {
            "X-WP-Nonce": WCSSM.nonce,
            "Content-Type": "application/json",
          },
          data: JSON.stringify(payload),
        })
          .done(function () {
            toast("Created! Redirecting…", true);
            setTimeout(function () {
              window.location = WCSSM.home + "stores";
            }, 800);
          })
          .fail(function (x) {
            toast("Error: " + (x.responseJSON?.message || x.statusText), false);
          });
      });
  };

  // -------------------------------------
  // STORES – Edit
  // -------------------------------------
  window.initStoreEdit = function (id) {
    const $msg = $("#s-msg");
    function toast(t, ok = true) {
      $msg
        .removeClass("err ok")
        .addClass(ok ? "ok" : "err")
        .text(t)
        .show();
    }

    function fill(s) {
      $("#s-name").val(s.name || "");
      $("#s-code").val(s.code || "");
      $("#s-city").val(s.city || "");
      $("#s-state").val(s.state || "");
      $("#s-quota").val(s.quota ?? "");
      $("#s-budget").val(s.budget ?? "");
    }

    $.ajax({
      url: WCSSM.rest + "stores/" + id,
      headers: { "X-WP-Nonce": WCSSM.nonce },
    })
      .done(fill)
      .fail((x) =>
        toast(
          "Load failed: " + (x.responseJSON?.message || x.statusText),
          false
        )
      );

    $("#s-save")
      .off("click")
      .on("click", function () {
        const body = {
          name: $("#s-name").val(),
          code: $("#s-code").val(),
          city: $("#s-city").val(),
          state: $("#s-state").val(),
          quota: $("#s-quota").val(),
          budget: $("#s-budget").val(),
        };
        if (!body.name) return toast("Name is required", false);

        toast("Saving…");
        $.ajax({
          url: WCSSM.rest + "stores/" + id,
          method: "PUT",
          headers: {
            "X-WP-Nonce": WCSSM.nonce,
            "Content-Type": "application/json",
          },
          data: JSON.stringify(body),
        })
          .done(function () {
            toast("Saved! Redirecting…", true);
            setTimeout(function () {
              window.location = WCSSM.home + "stores";
            }, 800);
          })
          .fail(function (x) {
            toast("Error: " + (x.responseJSON?.message || x.statusText), false);
          });
      });
  };
})(jQuery);

/* orders list functions */

/* ===== ORDERS MODULE ===== */
(function ($) {
  window.initOrdersList = function () {
    const $grid = $("#wcssm-orders-grid");
    const $flash = $("#wcssm-flash");
    const $status = $("#o-status");
    const $from = $("#o-from");
    const $to = $("#o-to");
    const $apply = $("#o-apply");
    const $clear = $("#o-clear");
    const $refresh = $("#o-refresh");

    const state = {
      page: 1,
      per_page: 20,
      total_pages: 1,
      total: 0,
      status: "",
      from: "",
      to: "",
    };

    function flash(msg, ok = true) {
      $flash
        .removeClass("is-ok is-err")
        .addClass(ok ? "is-ok" : "is-err")
        .text(msg)
        .stop(true, true)
        .fadeIn(120);
      setTimeout(() => $flash.fadeOut(180), 2500);
    }

    function escapeHtml(s) {
      return (s || "")
        .toString()
        .replace(
          /[&<>"]/g,
          (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c])
        );
    }

    function canApprove(slug) {
      return ["pending", "awaiting-approval", "on-hold"].includes(slug);
    }
    function canReject(slug) {
      return ["pending", "awaiting-approval", "on-hold", "approved"].includes(
        slug
      );
    }

    function render(items) {
      const head = `
        <div class="row head">
          <div>#</div>
          <div>Date</div>
          <div>Status</div>
          <div>Total</div>
          <div>Customer</div>
          <div>EDD</div>
          <div>Ref</div>
          <div>Actions</div>
        </div>`;

      const rows = (items || [])
        .map((o) => {
          const act = [];
          if (canApprove(o.status_slug)) {
            act.push(
              `<button class="btn btn-xs o-approve" data-id="${o.id}">Approve</button>`
            );
          }
          if (canReject(o.status_slug)) {
            act.push(
              `<button class="btn btn-xs btn-danger o-reject" data-id="${o.id}">Reject</button>`
            );
          }
          act.push(
            `<button class="btn btn-xs o-view" data-id="${o.id}">View</button>`
          );

          return `
          <div class="row">
            <div>${escapeHtml(o.number || "#" + o.id)}</div>
            <div>${escapeHtml(o.date || "")}</div>
            <div><span class="badge status-${escapeHtml(
              o.status_slug
            )}">${escapeHtml(o.status)}</span></div>
            <div>${o.total || ""}</div>
            <div>${escapeHtml(o.customer || "")}</div>
            <div>${escapeHtml(o.edd || "")}</div>
            <div>${escapeHtml(o.ref || "")}</div>
            <div class="acts">${act.join(" ")}</div>
          </div>`;
        })
        .join("");

      const pager = `
        <div class="pager-bar">
          <button type="button" class="btn pager-prev" ${
            state.page <= 1 ? "disabled" : ""
          }>← Prev</button>
          <span class="pager-info">Page ${state.page} / ${
        state.total_pages
      } · ${state.total} orders</span>
          <button type="button" class="btn pager-next" ${
            state.page >= state.total_pages ? "disabled" : ""
          }>Next →</button>
        </div>`;

      $grid.html(head + rows + pager);
    }

    function load() {
      $grid.html("Loading…");
      const qs = $.param({
        page: state.page,
        per_page: state.per_page,
        status: state.status || "",
        date_from: state.from || "",
        date_to: state.to || "",
      });
      $.ajax({
        url: WCSSM.rest + "orders?" + qs,
        headers: { "X-WP-Nonce": WCSSM.nonce },
        dataType: "json",
      })
        .done(function (d) {
          state.total = d.total || 0;
          state.total_pages = d.total_pages || 1;
          render(d.items || []);
        })
        .fail(function (x) {
          $grid.html("Error: " + (x.responseJSON?.message || x.statusText));
        });
    }

    // Filters
    $apply.on("click", function () {
      state.page = 1;
      state.status = $status.val() || "";
      state.from = $from.val() || "";
      state.to = $to.val() || "";
      load();
    });
    $clear.on("click", function () {
      $status.val("");
      $from.val("");
      $to.val("");
      state.page = 1;
      state.status = "";
      state.from = "";
      state.to = "";
      load();
    });
    $refresh.on("click", function () {
      load();
    });

    // Pager (delegated)
    $grid.on("click", ".pager-prev", function (e) {
      e.preventDefault();
      if (state.page > 1) {
        state.page--;
        load();
      }
    });
    $grid.on("click", ".pager-next", function (e) {
      e.preventDefault();
      if (state.page < state.total_pages) {
        state.page++;
        load();
      }
    });

    // Actions
    $grid.on("click", ".o-approve", function () {
      const id = parseInt($(this).data("id"), 10);
      $.ajax({
        url: WCSSM.rest + "orders/" + id + "/status",
        method: "POST",
        headers: {
          "X-WP-Nonce": WCSSM.nonce,
          "Content-Type": "application/json",
        },
        data: JSON.stringify({ status: "approved" }),
        dataType: "json",
      })
        .done(function () {
          flash("Order approved.", true);
          load();
        })
        .fail(function (x) {
          flash(
            "Approve failed: " + (x.responseJSON?.message || x.statusText),
            false
          );
        });
    });

    $grid.on("click", ".o-reject", function () {
      const id = parseInt($(this).data("id"), 10);
      const note = prompt("Add a rejection note (optional):") || "";
      $.ajax({
        url: WCSSM.rest + "orders/" + id + "/status",
        method: "POST",
        headers: {
          "X-WP-Nonce": WCSSM.nonce,
          "Content-Type": "application/json",
        },
        data: JSON.stringify({ status: "rejected", note }),
        dataType: "json",
      })
        .done(function () {
          flash("Order rejected.", true);
          load();
        })
        .fail(function (x) {
          flash(
            "Reject failed: " + (x.responseJSON?.message || x.statusText),
            false
          );
        });
    });

    // });

    $grid.on("click", ".o-view", function (e) {
      e.preventDefault();
      const id = parseInt($(this).data("id"), 10);
      if (!id) return;

      // Prefer a base passed from PHP; fallback to /manager/
      const base =
        window.WCSSM && WCSSM.manager_base ? WCSSM.manager_base : "/manager/";
      const url = base.replace(/\/+$/, "/") + "orders/view/" + id;

      if (e.ctrlKey || e.metaKey) {
        // open in new tab if user holds Ctrl/Cmd
        window.open(url, "_blank");
      } else {
        window.location.assign(url);
      }
    });

    load();
  };
})(jQuery);

window.initOrderView = function initOrderView(orderId) {
  var $wrap = jQuery("#order-detail");
  $wrap.html("<p>Loading...</p>");

  fetch((WCSSM.rest || "/wp-json/wcss/v1/") + "orders/" + orderId, {
    headers: { "X-WP-Nonce": WCSSM.nonce },
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (data && data.code) {
        $wrap.html(
          "<div class='flash-error'>Error: " +
            (data.message || "Failed") +
            "</div>"
        );
        return;
      }

      // ledger + store blocks (optional UI, shown only if present)
      var ledgerBlock = "";
      if (data && data.ledger) {
        var l = data.ledger;
        var fmt = function (n) {
          try {
            return new Intl.NumberFormat(undefined, {
              style: "currency",
              currency: l.currency,
            }).format(+n || 0);
          } catch (e) {
            return (l.currency || "") + " " + (n || 0);
          }
        };
        ledgerBlock =
          "<h3>Monthly Usage</h3>" +
          "<div class='ov-ledger'>" +
          "<p><strong>Month:</strong> " +
          (l.month || "") +
          "</p>" +
          "<p><strong>Orders:</strong> " +
          (l.used_orders || 0) +
          " / " +
          (l.quota || "∞") +
          "</p>" +
          "<p><strong>Spend:</strong> " +
          fmt(l.used_amount || 0) +
          " / " +
          (l.budget ? fmt(l.budget) : "∞") +
          "</p>" +
          "<p><strong>Remaining Orders:</strong> " +
          (l.quota ? Math.max(0, l.quota - (l.used_orders || 0)) : "∞") +
          "</p>" +
          "<p><strong>Remaining Budget:</strong> " +
          (l.budget
            ? fmt(Math.max(0, (l.budget || 0) - (l.used_amount || 0)))
            : "∞") +
          "</p>" +
          "</div>";
      }

      var storeLine = "";
      if (data && data.store && (data.store.name || data.store.id)) {
        storeLine =
          "<p><strong>Store:</strong> " +
          (data.store.name || "#" + data.store.id) +
          "</p>";
      }

      var html =
        "<div class='order-meta'>" +
        "<h2>Order #" +
        (data.number || orderId) +
        "</h2>" +
        "<p><strong>Date:</strong> " +
        (data.date || "") +
        "</p>" +
        "<p><strong>Status:</strong> " +
        (data.status || "") +
        "</p>" +
        "<p><strong>Total:</strong> " +
        (data.total_html || data.total || "") +
        "</p>" +
        "<p><strong>Customer:</strong> " +
        (data.customer || "") +
        "</p>" +
        storeLine +
        "</div>" +
        "<h3>Items</h3>" +
        "<table class='table'>" +
        "<thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Total</th></tr></thead>" +
        "<tbody>" +
        (Array.isArray(data.items)
          ? data.items
              .map(function (i) {
                return (
                  "<tr>" +
                  "<td>" +
                  (i.name || "") +
                  "</td>" +
                  "<td>" +
                  (i.sku
                    ? "<br><span class='muted sku'>SKU: " + i.sku + "</span>"
                    : "") +
                  "</td>" +
                  "<td>" +
                  (i.qty || 0) +
                  "</td>" +
                  "<td>" +
                  (i.total || "") +
                  "</td>" +
                  "</tr>"
                );
              })
              .join("")
          : "<tr><td colspan='3'>No items</td></tr>") +
        "</tbody>" +
        "</table>" +
        ledgerBlock +
        "<h3>Update Status</h3>" +
        "<div class='actions'>" +
        "<button class='btn btn-success' id='order-approve'>Approve</button> " +
        "<button class='btn btn-danger' id='order-reject'>Reject</button>" +
        "</div>" +
        "<div id='order-msg'></div>";

      $wrap.html(html);

      jQuery("#order-approve").on("click", function () {
        updateStatus("approved");
      });
      jQuery("#order-reject").on("click", function () {
        updateStatus("rejected");
      });

      function updateStatus(newStatus, opts) {
        opts = opts || {};
        var payload = { status: newStatus };
        if (opts.override) payload.override = 1;
        if (opts.note) payload.note = opts.note;

        fetch(
          (WCSSM.rest || "/wp-json/wcss/v1/") + "orders/" + orderId + "/status",
          {
            method: "POST",
            headers: {
              "X-WP-Nonce": WCSSM.nonce,
              "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
          }
        )
          .then(function (r) {
            // Handle 409 (limit exceeded) specially
            if (r.status === 409)
              return r.json().then(function (j) {
                j.__status = 409;
                return j;
              });
            return r.json();
          })
          .then(function (res) {
            // Limit exceeded → show confirm with details, allow override
            if (res && res.__status === 409 && res.data) {
              var d = res.data;
              var money = function (n) {
                try {
                  return new Intl.NumberFormat(undefined, {
                    style: "currency",
                    currency: d.currency,
                  }).format(+n || 0);
                } catch (e) {
                  return (d.currency || "") + " " + (n || 0);
                }
              };
              var msg = "Approving this order exceeds the store limits:\n";
              if (d.quota)
                msg += "• Orders: " + d.will_orders + " / " + d.quota + "\n";
              if (d.budget)
                msg +=
                  "• Spend: " +
                  money(d.will_spend) +
                  " / " +
                  money(d.budget) +
                  "\n";
              msg += "\nProceed anyway?";
              if (window.confirm(msg)) {
                var note = window.prompt(
                  "Add a note (optional):",
                  "Approved with override"
                );
                updateStatus("approved", { override: true, note: note || "" });
              } else {
                jQuery("#order-msg").html(
                  "<div class='flash-error'>Approval cancelled.</div>"
                );
              }
              return;
            }

            if (res && res.ok) {
              jQuery("#order-msg").html(
                "<div class='flash-success'>Status updated to " +
                  newStatus +
                  "!</div>"
              );
              // go back to orders list after a short pause
              setTimeout(function () {
                window.location.href =
                  (WCSSM.manager_base || "/manager/") + "orders";
              }, 500);
            } else {
              jQuery("#order-msg").html(
                "<div class='flash-error'>" +
                  (res && res.message ? res.message : "Failed") +
                  "</div>"
              );
            }
          })
          .catch(function (e) {
            jQuery("#order-msg").html(
              "<div class='flash-error'>Request failed</div>"
            );
          });
      }
    })
    .catch(function () {
      $wrap.html("<div class='flash-error'>Failed to load order.</div>");
    });
};

// Global bootstrap for page-specific initializers
// jQuery(function ($) {
//   if (!window.WCSSM) return;
//   console.log("initiator....");
//   // Orders → view
//   if (WCSSM.view === "orders" && WCSSM.action === "view" && WCSSM.id) {
//     if (typeof window.initOrderView === "function") {
//       window.initOrderView(WCSSM.id);
//       console.log("initiator.... if");
//     } else {
//       console.log("initiator.... else");
//       // In case scripts are still settling, try once more shortly
//       setTimeout(function () {
//         if (typeof window.initOrderView === "function") {
//           window.initOrderView(WCSSM.id);
//         }
//       }, 50);
//     }
//   }

//   // (You can keep similar bootstraps for products/stores, etc.)
// });
