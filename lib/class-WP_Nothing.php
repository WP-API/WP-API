<?php

if ( ! class_exists('WP_Nothing') ) {
    /**
     * A Completely Empty Class to represent Null or Empty values.
     *
     * You can use this to truly determine whether an option exists by saying:
     *
     *  $option = get_option( 'non_existent_opt', new WP_Nothing() );
     *
     *  if ( is_a( $option, 'WP_Nothing' ) ) {
     *      // Option Really doesn't Exist
     *  }
     *
     * There are plenty of other uses for this too but basically Don't Store this ever, you should never need to use
     *  this to represent a real value
     *
     *
     * @author  Eddie Hurtig <hurtige@sudbury.ma.us>
     */
    class WP_Nothing {

    }
}