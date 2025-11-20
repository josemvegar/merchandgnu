/**
 * WooIntcomex Admin JS (cleaned)
 * @since 1.0.0
 */

var jQuery = window.jQuery
var table
var Global = window.Global
var combinar_json_zecat_cdo = window.combinar_json_zecat_cdo
var fgetProductsZecat = window.fgetProductsZecat
var fgetProductsCdo = window.fgetProductsCdo
var fgetProductsPromoImport = window.fgetProductsPromoImport
var fcreateWooCommerceProductsFromJsonGlobo = window.fcreateWooCommerceProductsFromJsonGlobo
var fUpdateStockGlobo = window.fUpdateStockGlobo
var fUpdatePriceGlobo = window.fUpdatePriceGlobo
var fUpdateStockCDO = window.fUpdateStockCDO
var fUpdateStockCDOGlobo = window.fUpdateStockCDOGlobo
var fUpdateStockZecatGlobo = window.fUpdateStockZecatGlobo
var fUpdatePriceZecat = window.fUpdatePriceZecat
var fUpdatePriceCDO = window.fUpdatePriceCDO
var fcreateWooCommerceProductsFromZecatJson2 = window.fcreateWooCommerceProductsFromZecatJson2
var fcreateWooCommerceProductsFromCDOJson = window.fcreateWooCommerceProductsFromCDOJson
var fDeleteProductsCatalogo = window.fDeleteProductsCatalogo

jQuery(document).ready(function () {
  table = jQuery("#MerchanCatalog").DataTable({
    dom: "Bfrtip",
    buttons: ["copy", "csv", "excel", "pdf", "print"],
    responsive: true,
    ajax: {
      url: "/merchant/wp-admin/admin-ajax.php?action=datatables_endpoint_merchan",
      dataSrc: "data",
    },
    select: { style: "multi" },
    columns: [
      { data: "ID" },
      { data: "sku_proveedor" },
      { data: "nombre_del_producto" },
      { data: "descripcion" },
      { data: "precio" },
      { data: "image" },
      { data: "stock" },
      { data: "proveedor" },
    ],
  })

  function showLoader() {
    jQuery(".loadermerchan").show()
    jQuery(".popup-overlay-merchan").fadeIn("slow")
  }

  function hideLoader() {
    jQuery(".loadermerchan").hide()
    jQuery(".popup-overlay-merchan").fadeOut("slow")
  }

  function ajaxAction(buttonSelector, ajaxData, reloadOnSuccess = true) {
    jQuery(buttonSelector).html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass("disabled")
    jQuery
      .ajax({
        type: "POST",
        url: Global.url,
        data: ajaxData,
        beforeSend: showLoader,
      })
      .done(function (response) {
        jQuery(buttonSelector).removeClass("disabled")
        hideLoader()
        alert("Actualización completada")
        if (reloadOnSuccess) location.reload()
      })
      .fail(function () {
        jQuery(buttonSelector).removeClass("disabled")
        hideLoader()
        alert("Error en la comunicación con el servidor.")
      })
  }

  jQuery("#ActualizarListaProductos").on("click", function (e) {
    e.preventDefault()
    ajaxAction(this, { action: combinar_json_zecat_cdo.action, nonce: Global.nonce })
  })

  jQuery("#ActualizarCatalogoZecat").on("click", function (e) {
    e.preventDefault()
    ajaxAction(this, { action: fgetProductsZecat.action, nonce: Global.nonce })
  })

  jQuery("#ActualizarCatalogoCDO").on("click", function (e) {
    e.preventDefault()
    ajaxAction(this, { action: fgetProductsCdo.action, nonce: Global.nonce })
  })

  jQuery("#ActualizarCatalogoPromoImport").on("click", function (e) {
    e.preventDefault()
    ajaxAction(this, { action: fgetProductsPromoImport.action, nonce: Global.nonce })
  })

  function publicarProductos(provider, fcreateAction, tamanoLote) {
    var totalProductos = 0
    var productosActualizados = 0
    var offsetActual = 0

    function actualizarLote(offset) {
      jQuery
        .ajax({
          type: "POST",
          url: Global.url,
          data: {
            action: fcreateAction.action,
            offset: offset,
            tamano_lote: tamanoLote,
            nonce: fcreateAction.nonce,
            provider: provider,
          },
          beforeSend: showLoader,
        })
        .done(function (response) {
          if (!response.success) {
            hideLoader()
            alert("Error en la actualización: " + (response.data || "Error desconocido"))
            return
          }

          totalProductos = response.data.total || totalProductos
          productosActualizados += response.data.creados || 0
          offsetActual = response.data.offset || offset + tamanoLote

          jQuery("#totalProducts").text(totalProductos)
          jQuery("#publishedProducts").text(productosActualizados)

          var porcentaje = Math.min((offsetActual / Math.max(1, totalProductos)) * 100, 100)
          jQuery("#progress").css("width", porcentaje + "%")
          jQuery("#progress").text(Math.round(porcentaje) + "%")

          if (offsetActual < totalProductos) {
            actualizarLote(offsetActual)
          } else {
            hideLoader()
            if (response.data.errors && response.data.errors.length > 0) alert("Proceso completado con algunos errores.")
            else alert("Actualización completada exitosamente.")
          }
        })
        .fail(function () {
          hideLoader()
          alert("Error en la comunicación con el servidor.")
        })
    }

    actualizarLote(0)
  }

  jQuery("#PublicarProductosPromoImport").on("click", function () {
    publicarProductos("promoimport", fcreateWooCommerceProductsFromJsonGlobo, 2)
  })

  jQuery("#PublicarProductosZecat").on("click", function () {
    publicarProductos("ZECAT", fcreateWooCommerceProductsFromJsonGlobo, 2)
  })

  jQuery("#PublicarProductosCDO").on("click", function () {
    publicarProductos("CDO", fcreateWooCommerceProductsFromJsonGlobo, 2)
  })

  jQuery("#EliminarProductosCatalogo").on("click", function (e) {
    e.preventDefault()
    if (!confirm("⚠️ ADVERTENCIA: Esta acción eliminará PERMANENTEMENTE productos. ¿Continuar?")) return

    var totalProductos = 0
    var productosEliminados = 0
    var offsetActual = 0
    var tamanoLote = 10

    function eliminarLote(offset) {
      jQuery
        .ajax({
          type: "POST",
          url: fDeleteProductsCatalogo.ajax_url,
          data: { action: fDeleteProductsCatalogo.action, offset: offset, tamano_lote: tamanoLote, nonce: fDeleteProductsCatalogo.nonce },
          beforeSend: showLoader,
        })
        .done(function (response) {
          if (!response.success) {
            hideLoader()
            alert("Error en la eliminación: " + (response.data.message || "Error desconocido"))
            return
          }

          totalProductos = response.data.total || totalProductos
          productosEliminados += response.data.eliminados || 0
          offsetActual = response.data.offset || offset + tamanoLote

          jQuery("#totalProducts").text(totalProductos)
          jQuery("#publishedProducts").text(productosEliminados)

          var porcentaje = Math.min((offsetActual / Math.max(1, totalProductos)) * 100, 100)
          jQuery("#progress").css("width", porcentaje + "%")
          jQuery("#progress").text(Math.round(porcentaje) + "%")

          if (offsetActual < totalProductos) eliminarLote(offsetActual)
          else {
            hideLoader()
            if (response.data.errors && response.data.errors.length > 0) alert("Proceso completado con algunos errores. Productos eliminados: " + productosEliminados)
            else alert("Eliminación completada exitosamente. Total eliminados: " + productosEliminados)
            location.reload()
          }
        })
        .fail(function () {
          hideLoader()
          alert("Error en la comunicación con el servidor.")
        })
    }

    eliminarLote(0)
  })
})
