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
    var tamanoLote = 5

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

  // Función unificada para todos los proveedores
  function actualizarStockProveedor(provider) {
    var totalProductos = 0;
    var productosActualizados = 0;
    var offsetActual = 0;
    var tamanoLote = 10;

    function actualizarLote(offset) {
      jQuery.ajax({
        url: fUpdateStockGlobo.ajax_url,
        type: 'POST',
        data: {
          action: fUpdateStockGlobo.action,
          provider: provider,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: getNonceByProvider(provider)
        },
        beforeSend: function () {
          jQuery(".loadermerchan").show();
          jQuery('.popup-overlay-merchan').fadeIn('slow');
          jQuery('#providerName').text(provider.toUpperCase());
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            offsetActual = response.data.offset;

            console.log('Proveedor: ' + provider);
            console.log('Total productos: ' + totalProductos);
            console.log('Actualizados en este lote: ' + response.data.actualizados);
            console.log('Total actualizados: ' + productosActualizados);
            console.log('Siguiente offset: ' + offsetActual);

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((offsetActual / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');
            jQuery('#progress').text(Math.round(porcentaje) + '%');

            // Continuar si hay más productos
            if (offsetActual < totalProductos) {
              console.log('Continuando con siguiente lote...');
              actualizarLote(offsetActual);
            } else {
              console.log('Proceso completado para ' + provider);
              jQuery(".loadermerchan").hide();
              jQuery('.popup-overlay-merchan').fadeOut('slow');
              alert('Actualización de stock completada para ' + provider.toUpperCase() + '. Productos actualizados: ' + productosActualizados);
            }
          } else {
            console.log('Error en respuesta:', response.data);
            alert('Error en la actualización: ' + (response.data || 'Error desconocido'));
            jQuery(".loadermerchan").hide();
            jQuery('.popup-overlay-merchan').fadeOut('slow');
          }
        },
        error: function (xhr, status, error) {
          console.log('Error AJAX:', error);
          alert('Error en la comunicación con el servidor.');
          jQuery(".loadermerchan").hide();
          jQuery('.popup-overlay-merchan').fadeOut('slow');
        }
      });
    }

    // Helper para obtener nonce según proveedor
    function getNonceByProvider(provider) {
      var nonces = {
        'promoimport': fUpdateStockGlobo.nonce_promoimport,
        'zecat': fUpdateStockGlobo.nonce_zecat,
        'cdo': fUpdateStockGlobo.nonce_cdo
      };
      return nonces[provider];
    }

    // Iniciar el proceso
    actualizarLote(0);
  }

  jQuery("#ActualizarStockPromoImport").click(function (e) {
    e.preventDefault();
    actualizarStockProveedor('promoimport');
  });

  jQuery("#ActualizarStockZecat").click(function (e) {
    e.preventDefault();
    actualizarStockProveedor('zecat');
  });

  jQuery("#ActualizarStockCDO").click(function (e) {
    e.preventDefault();
    actualizarStockProveedor('cdo');
  });

  // Función unificada para actualización de precios de todos los proveedores
  function actualizarPrecioProveedor(provider) {
    var totalProductos = 0;
    var productosActualizados = 0;
    var offsetActual = 0;
    var tamanoLote = 10;

    function actualizarLote(offset) {
      jQuery.ajax({
        url: fUpdatePriceGlobo.ajax_url,
        type: 'POST',
        data: {
          action: fUpdatePriceGlobo.action,
          provider: provider,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: getNonceByProvider(provider)
        },
        beforeSend: function () {
          jQuery(".loadermerchan").show();
          jQuery('.popup-overlay-merchan').fadeIn('slow');
          jQuery('#providerName').text(provider.toUpperCase() + ' - PRECIOS');
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            offsetActual = response.data.offset;

            console.log('Proveedor Precios: ' + provider);
            console.log('Total productos: ' + totalProductos);
            console.log('Actualizados en este lote: ' + response.data.actualizados);
            console.log('Total actualizados: ' + productosActualizados);
            console.log('Siguiente offset: ' + offsetActual);

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((offsetActual / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');
            jQuery('#progress').text(Math.round(porcentaje) + '%');

            // Continuar si hay más productos
            if (offsetActual < totalProductos) {
              console.log('Continuando con siguiente lote de precios...');
              actualizarLote(offsetActual);
            } else {
              console.log('Proceso de precios completado para ' + provider);
              jQuery(".loadermerchan").hide();
              jQuery('.popup-overlay-merchan').fadeOut('slow');
              alert('Actualización de precios completada para ' + provider.toUpperCase() + '. Productos actualizados: ' + productosActualizados);
            }
          } else {
            console.log('Error en respuesta precios:', response.data);
            alert('Error en la actualización de precios: ' + (response.data || 'Error desconocido'));
            jQuery(".loadermerchan").hide();
            jQuery('.popup-overlay-merchan').fadeOut('slow');
          }
        },
        error: function (xhr, status, error) {
          console.log('Error AJAX precios:', error);
          alert('Error en la comunicación con el servidor.');
          jQuery(".loadermerchan").hide();
          jQuery('.popup-overlay-merchan').fadeOut('slow');
        }
      });
    }

    // Helper para obtener nonce según proveedor
    function getNonceByProvider(provider) {
      var nonces = {
        'promoimport': fUpdatePriceGlobo.nonce_promoimport,
        'zecat': fUpdatePriceGlobo.nonce_zecat,
        'cdo': fUpdatePriceGlobo.nonce_cdo
      };
      return nonces[provider];
    }

    // Iniciar el proceso
    actualizarLote(0);
  }

  // Event handlers para precios
  jQuery("#ActualizarPrecioPromoImport").click(function (e) {
    e.preventDefault();
    actualizarPrecioProveedor('promoimport');
  });

  jQuery("#ActualizarPrecioZecat").click(function (e) {
    e.preventDefault();
    actualizarPrecioProveedor('zecat');
  });

  jQuery("#ActualizarPrecioCDO").click(function (e) {
    e.preventDefault();
    actualizarPrecioProveedor('cdo');
  });
  
});