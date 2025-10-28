/**
 * WooIntcomex Admin JS
 *
 * @since 1.0.0
 */

jQuery(document).ready(function () {

  table = jQuery('#MerchanCatalog').DataTable({
    "dom": 'Bfrtip',
    "buttons": [
      'copy', 'csv', 'excel', 'pdf', 'print', 'selected',
      'selectedSingle',
      'selectAll',
      'selectNone',
      'selectRows',
      'selectColumns',
      'selectCells'
    ],
    "responsive": true,
    "ajax": {
      "url": "/merchant/wp-admin/admin-ajax.php?action=datatables_endpoint_merchan",
      "dataSrc": "data"
    },
    "select": {
      "style": 'multi'
    },
    "columns": [
      { "data": 'ID' },
      { "data": 'sku_proveedor' },
      { "data": 'nombre_del_producto' },
      { "data": 'descripcion' },
      { "data": 'precio' },
      { "data": 'image' },
      { "data": 'stock' },
      { "data": 'proveedor' }
    ],
  });


  jQuery("#ActualizarListaProductos").click(function (e) {
    e.preventDefault();
    jQuery("#ActualizarListaProductos").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: combinar_json_zecat_cdo.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#ActualizarListaProductos").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").hide();
        jQuery('.popup-overlay-merchan').fadeOut('slow');
        location.reload();
      }
    });
  });


  jQuery("#ActualizarCatalogoZecat").click(function (e) {
    e.preventDefault();

    jQuery("#ActualizarCatalogoZecat").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fgetProductsZecat.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#ActualizarCatalogoZecat").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").hide();
        jQuery('.popup-overlay-merchan').fadeOut('slow');
        location.reload();
      }
    });
  });

  jQuery("#ActualizarCatalogoCDO").click(function (e) {
    e.preventDefault();

    jQuery("#ActualizarCatalogoCDO").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fgetProductsCdo.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        //jQuery("#ActualizarCatalogoCDO .fa-spin").remove();
        jQuery("#ActualizarCatalogoCDO").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").hide();
        jQuery('.popup-overlay-merchan').fadeOut('slow');
        location.reload();
      }
    });
  });


  jQuery("#ActualizarCatalogoPromoImport").click(function (e) {
    e.preventDefault();

    jQuery("#ActualizarCatalogoPromoImport").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fgetProductsPromoImport.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        //jQuery("#ActualizarCatalogoPromoImport .fa-spin").remove();
        jQuery("#ActualizarCatalogoPromoImport").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").hide();
        jQuery('.popup-overlay-merchan').fadeOut('slow');
        location.reload();
      }
    });
  });

  jQuery('#PublicarProductosPromoImport').on('click', function () {


    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      if (offset > totalProductos) {
        alert('Actualización completada.');
        jQuery('#newproduct').text(productosActualizados);
        return;
      }
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fcreateWooCommerceProductsFromPromoImportJsonGlobo.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fcreateWooCommerceProductsFromPromoImportJsonGlobo.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);

            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((productosActualizados / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');


            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }

          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });


  jQuery('#PublicarProductosZecat').on('click', function () {
    var totalProductos = 0;
    var productosActualizados = 0;
    var offsetActual = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fcreateWooCommerceProductsFromZecatJsonGlobo.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fcreateWooCommerceProductsFromZecatJsonGlobo.nonce
        },
        beforeSend: function () {
          jQuery(".loadermerchan").show();
          jQuery('.popup-overlay-merchan').fadeIn('slow');
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.creados; // ✅ Cambiado de 'actualizados' a 'creados'
            offsetActual = response.data.offset;

            console.log('Total productos: ' + totalProductos);
            console.log('Creados en este lote: ' + response.data.creados);
            console.log('Total creados: ' + productosActualizados);
            console.log('Siguiente offset: ' + offsetActual);

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // ✅ CORRECCIÓN: Calcular porcentaje basado en OFFSET, no en productos creados
            var porcentaje = Math.min((offsetActual / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');
            jQuery('#progress').text(Math.round(porcentaje) + '%');

            // ✅ CORRECCIÓN: Continuar mientras el offset sea menor al total
            if (offsetActual < totalProductos) {
              console.log('Continuando con siguiente lote...');
              actualizarLote(offsetActual);
            } else {
              console.log('Proceso completado');
              jQuery(".loadermerchan").hide();
              jQuery('.popup-overlay-merchan').fadeOut('slow');

              if (response.data.errors && response.data.errors.length > 0) {
                alert('Proceso completado con algunos errores. Revisa la consola para más detalles.');
                console.log('Errores:', response.data.errors);
              } else {
                alert('Actualización completada exitosamente.');
              }
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

    // Iniciar el proceso
    actualizarLote(0);
  });


  jQuery('#PublicarProductosCDO').on('click', function () {


    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      if (offset > totalProductos) {
        alert('Actualización completada.');
        jQuery('#newproduct').text(productosActualizados);
        return;
      }
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fcreateWooCommerceProductsFromCDOJsonGlobo.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fcreateWooCommerceProductsFromCDOJsonGlobo.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);

            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((productosActualizados / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');

            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }

          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });

  jQuery("#ActualizarStockPromoImport").click(function (e) {

    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      if (offset > totalProductos) {
        alert('Actualización completada.');
        jQuery('#newproduct').text(productosActualizados);
        return;
      }
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fUpdateStockPromoImportGlobo.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fUpdateStockPromoImportGlobo.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);


            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((productosActualizados / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');


            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }

          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });

  jQuery("#ActualizarStockZecat").click(function (e) {

    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      if (offset > totalProductos) {
        alert('Actualización completada.');
        jQuery('#newproduct').text(productosActualizados);
        return;
      }
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fUpdateStockZecatGlobo.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fUpdateStockZecatGlobo.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);


            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((productosActualizados / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');


            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }

          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });


  jQuery("#ActualizarStockCDO").click(function (e) {

    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      if (offset > totalProductos) {
        alert('Actualización completada.');
        jQuery('#newproduct').text(productosActualizados);
        return;
      }
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fUpdateStockCDOGlobo.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fUpdateStockCDOGlobo.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;

            // Actualizar DOM
            jQuery('#totalProducts').text(totalProductos);
            jQuery('#publishedProducts').text(productosActualizados);

            // Calcular porcentaje
            var porcentaje = Math.min((productosActualizados / totalProductos) * 100, 100);
            jQuery('#progress').css('width', porcentaje + '%');
            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }

          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });


  jQuery("#ActualizarPrecioZecat").click(function (e) {

    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fUpdatePriceZecat.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fUpdatePriceZecat.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);

            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }
          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });

  jQuery("#ActualizarPrecioCDO").click(function (e) {

    var totalProductos = 0;
    var productosActualizados = 0;
    var tamanoLote = 2; // Cambia este valor según tus necesidades

    function actualizarLote(offset) {
      jQuery.ajax({
        url: Global.url,
        type: 'POST',
        data: {
          action: fUpdatePriceCDO.action,
          offset: offset,
          tamano_lote: tamanoLote,
          nonce: fUpdatePriceCDO.nonce
        },
        success: function (response) {
          if (response.success) {
            totalProductos = response.data.total;
            productosActualizados += response.data.actualizados;
            console.log('total: ' + response.data.total);
            console.log('actualizados: ' + response.data.actualizados);
            console.log('offset: ' + response.data.offset);

            if (productosActualizados < totalProductos) {
              actualizarLote(offset + tamanoLote);
            } else {
              alert('Actualización completada.');
            }
          } else {
            console.log(response.data);
            alert('Error en la actualización.');
          }
        },
        error: function () {
          console.log(response.data);
          alert('Error en la comunicación con el servidor.');
        }
      });
    }

    actualizarLote(0);
  });

  jQuery("#actualizarstockcdozecatv1").click(function (e) {
    e.preventDefault();

    jQuery("#actualizarstockcdozecatv1").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fUpdateStockCDO.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#actualizarstockcdozecatv1 .fa-spin").remove();
        jQuery("#actualizarstockcdozecatv1").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });

  jQuery("#actualizarstockcdoglobo").click(function (e) {
    e.preventDefault();

    jQuery("#actualizarstockcdoglobo").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fUpdateStockCDOGlobo.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#actualizarstockcdoglobo .fa-spin").remove();
        jQuery("#actualizarstockcdoglobo").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });


  jQuery("#actualizarstockzecatglobo").click(function (e) {
    e.preventDefault();

    jQuery("#actualizarstockzecatglobo").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fUpdateStockZecatGlobo.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#actualizarstockzecatglobo .fa-spin").remove();
        jQuery("#actualizarstockzecatglobo").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });

  jQuery("#actualizarpreciozecatcdo").click(function (e) {
    e.preventDefault();

    jQuery("#actualizarpreciozecatcdo").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fUpdatePriceZecat.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#actualizarpreciozecatcdo .fa-spin").remove();
        jQuery("#actualizarpreciozecatcdo").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });

  jQuery("#actualizarpreciozecatcdov1").click(function (e) {
    e.preventDefault();

    jQuery("#actualizarpreciozecatcdov1").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fUpdatePriceCDO.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#actualizarpreciozecatcdov1 .fa-spin").remove();
        jQuery("#actualizarpreciozecatcdov1").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });


  /*
    jQuery("#actualizarpreciozecatcdov1").click(function (e) {
      e.preventDefault();
  
      jQuery("#actualizarpreciozecatcdov1").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');
  
      function loadPriceBatch(page = 0, totalPages = 5) {
          jQuery.ajax({
              type: "POST",
              url: Global.url,
              data: {
                  action: fUpdatePriceCDO.action,
                  nonce: Global.nonce,
                  page: page // Pasar la página actual al servidor
              },
              beforeSend: function() {
                  jQuery(".loaderIntComex").show();
                  jQuery('.popup-overlay').fadeIn('slow');
              },
              success: function(data) {
                  if (data && data.success) {
                      // Si quedan más páginas por procesar, continuar con la siguiente
                      if (data.data && data.data.hasMorePages) {
                          console.log(`Procesando página ${page + 1} de ${totalPages}`);
                          sessionStorage.setItem('currentPricePage', page + 1);
                          loadPriceBatch(page + 1, totalPages); // Llamar de nuevo para procesar la siguiente página
                      } else {
                          // Si no hay más páginas, completar el proceso
                          sessionStorage.removeItem('currentPricePage');
                          jQuery("#actualizarpreciozecatcdov1 .fa-spin").remove();
                          jQuery("#actualizarpreciozecatcdov1").html('Actualizar Precios');
                          jQuery("#actualizarpreciozecatcdov1").removeClass('disabled');
                          alert('Actualización de precios completada');
                          jQuery(".loaderIntComex").hide();
                          jQuery('.popup-overlay').fadeOut('slow');
                          // location.reload(); // Si necesitas recargar la página después de completar
                      }
                  } else {
                      var errorMessage = data && data.data && data.data.message ? data.data.message : 'Error desconocido';
                      console.log('Error al iniciar la actualización: ' + errorMessage);
                      alert('Error: ' + errorMessage);
                      sessionStorage.removeItem('currentPricePage');
                  }
              },
              error: function() {
                  // Si hay un error, volver a intentar desde la página actual
                  var retryPage = parseInt(sessionStorage.getItem('currentPricePage')) || page;
                  loadPriceBatch(retryPage, totalPages);
              }
          });
      }
  
      // Primera llamada para obtener el número total de páginas
      jQuery.ajax({
          type: "POST",
          url: Global.url,
          data: {
              action: fUpdatePriceCDO.action,
              nonce: Global.nonce,
              page: 0
          },
          success: function(data) {
            console.log(data);
              if (data && data.success) {
                  var totalPages = Math.ceil(data.total_products / 5); // Cambiado a procesar 1 producto por página
                  console.log(`Total de páginas a procesar: ${totalPages}`);
                  
                  var currentPage = parseInt(sessionStorage.getItem('currentPricePage')) || 0;
                  loadPriceBatch(currentPage, totalPages); // Iniciar el proceso de actualización por lotes
              } else {
                  var errorMessage = data && data.data && data.data.message ? data.data.message : 'Error desconocido';
                  console.log('Error al iniciar la actualización: ' + errorMessage);
                  alert('Error: ' + errorMessage);
              }
          }
      });
    });
  */


  jQuery("#publicarproductoszecatcdo").click(function (e) {
    e.preventDefault();

    jQuery("#publicarproductoszecatcdo").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fcreateWooCommerceProductsFromZecatJson2.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#publicarproductoszecatcdo .fa-spin").remove();
        jQuery("#publicarproductoszecatcdo").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });

  jQuery("#publicarproductoszecatcdov1").click(function (e) {
    e.preventDefault();

    jQuery("#publicarproductoszecatcdov1").html('<i class="fa fa-spinner fa-spin" style="font-size:20px"></i>').addClass('disabled');

    jQuery.ajax({
      type: "POST",
      url: Global.url,
      data: {
        action: fcreateWooCommerceProductsFromCDOJson.action,
        nonce: Global.nonce,

      },
      beforeSend: function () {
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
      },
      success: function (data) {
        jQuery("#publicarproductoszecatcdov1 .fa-spin").remove();
        jQuery("#publicarproductoszecatcdov1").removeClass('disabled');
        console.log(data);

        alert('Actualización completada');
        jQuery(".loadermerchan").show();
        jQuery('.popup-overlay-merchan').fadeIn('slow');
        //location.reload();
      }
    });
  });





});