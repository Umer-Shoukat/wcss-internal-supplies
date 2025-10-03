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

// -------------------------------------
// STORES – List
// -------------------------------------

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
