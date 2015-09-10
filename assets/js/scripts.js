jQuery( document ).ready( function ( e ) {

	if( jQuery( 'input#woocommerce_wd_pickup_location' ).length ) {

		jQuery( 'input#woocommerce_wd_pickup_location' ).geocomplete({
			types: [ 'geocode', 'establishment' ],
		}).bind( 'geocode:result', function( event, result ) {

			var latlng = '';

			if( result.geometry.location.k && result.geometry.location.D ) {

				var latitude = result.geometry.location.k;
				var longitude = result.geometry.location.D;

				if( latitude && longitude ) {
					var latlng = latitude + ', ' + longitude
					jQuery( '#woocommerce_wd_pickup_coords' ).val( latlng );
				}
			}

			if( ! latlng ) {
				alert( wc_wumdrop.no_coords );
			}
		});
	}

});