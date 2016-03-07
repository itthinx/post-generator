/**
 * generator.js
 *
 * Copyright (c) 2014-2016 "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author itthinx
 * @package generator
 * @since 1.0.0
 */

var ixpostgen = {
	running : false,
	generating : false,
	timeout : null,
	limit : null
};

/**
 * Post generator query.
 */
ixpostgen.generate = function() {

	if ( typeof args === "undefined" ) {
		args = {};
	}

	var $status = jQuery( "#generator-status" ),
		$update = jQuery( "#generator-update" ),
		$blinker = jQuery( "#generator-blinker" );

	$blinker.addClass( 'blinker' );
	$status.html('<p>Generating</p>' );
	if ( !ixpostgen.generating ) {
		ixpostgen.generating = true;
		jQuery.ajax({
				type : 'POST',
				url  : ixpostgen.url,
				data : { "action" : "generator", "nonce" : ixpostgen.nonce },
				complete : function() {
					ixpostgen.generating = false;
					$blinker.removeClass('blinker');
				},
				success : function ( data ) {
					if ( typeof data.total !== "undefined" ) {
						$update.html( '<p>Total Posts: ' + data.total + '</p>' );
						if ( ixpostgen.limit !== null ) {
							if ( data.total >= ixpostgen.limit ) {
								ixpostgen.stop();
							}
						}
					}
				},
				dataType : "json"
		});
	}
};

ixpostgen.start = function( url, nonce ) {
	if ( !ixpostgen.running ) {
		ixpostgen.running = true;
		ixpostgen.url = url;
		ixpostgen.nonce = nonce;
		ixpostgen.exec();
		var $status = jQuery( "#generator-status" );
		$status.html( '<p>Running</p>' );
	}
};

ixpostgen.exec = function() {
	ixpostgen.timeout = setTimeout(
		function() {
			if ( ixpostgen.running ) {
				if ( !ixpostgen.generating ) {
					ixpostgen.generate();
				}
				ixpostgen.exec();
			}
		},
		1000
	);
};

ixpostgen.stop = function() {
	if ( ixpostgen.running ) {
		ixpostgen.running = false;
		clearTimeout( ixpostgen.timeout );
		var $status = jQuery( "#generator-status" );
		$status.html( '<p>Stopped</p>' );
	}
};
