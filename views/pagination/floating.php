<div class="cl4_nav">
	<div class="cl4_nav_pages">
<?php

// the ellipsis var needs to have a spaces on either end for formatting
$ellipsis = ' <span class="ellipsis">...</span> ';

// only show the nav portion when there is more than 1 page
// if there is only 1 page then it will display only the number of records showing
if ($total_pages > 1) {
	if ($previous_page !== FALSE) {
		echo HTML::anchor($page->url($previous_page), '<span class="cl4_icon cl4_left"></span>' . __('Previous') . '</span>', array('class' => 'previous'));
	} else {
		echo '<span class="no_previous"><span class="cl4_icon cl4_no_left"></span>', __('Previous'), '</span>';
	}
	echo ' ';

	if ($first_page !== FALSE) {
		echo HTML::anchor($page->url($first_page), __('First'));
	} else {
		echo __('First');
	}
	echo ' ';

	if ($total_pages < 20) {
	    // basic nav for less than 20 pages
	    for ($i = 1; $i <= $total_pages; $i++) {
	        if ($i == $current_page) {
	            echo '<span class="current_page">', $i, '</span>', EOL;
	        } else {
        		echo HTML::anchor($page->url($i), $i), EOL;
	        } // if
	    } // for

	} else {
	    // there are more than 20 pages
	    if ($offset == 0) {
	        // at first page
	        echo ' <span class="current_page">1</span> ', EOL;

	        // display the next 5 pages with links
	        for ($i = 2; $i <= 6; $i++) {
	            $new_offset = ($i-1) * $items_per_page;
	            echo HTML::anchor($page->url($i), $i), EOL;
	        } // for

	        // display the next 3 links as 10, 20, 30
	        for ($i=10; $i<=30; $i+=10) {
	            if ($i > 1 && $i < ($total_pages - 1)) {
	                $new_offset = ($i-1) * $items_per_page;
	                echo $ellipsis, HTML::anchor($page->url($i), $i), EOL;
	            }
	        } // for

	        // display the last page
	        $new_offset = ($total_pages * $items_per_page) - $items_per_page;
	        echo $ellipsis, HTML::anchor($page->url($total_pages), $total_pages), EOL;

	    } else if ($offset == ($items_per_page * $total_pages - $items_per_page)) {
	        // at last page
	        // provide link to first page
	        echo HTML::anchor($page->url(1), 1), $ellipsis, EOL;

	        // display 10 page links backwards from the last page
	        for ($i=$total_pages - 30; $i<=$total_pages - 10; $i+=10) {
	            if ($i > 1 && $i < ($total_pages - 1)) {
	                $new_offset = ($i-1) * $items_per_page;
	                echo HTML::anchor($page->url($i), $i), $ellipsis, EOL;
	            }
	        } // for

	        // display the 5 pages before the last page
	        for ($i=$total_pages - 5; $i<$total_pages; $i++) {
	            $new_offset = ($i-1) * $items_per_page;
	            echo HTML::anchor($page->url($i), $i), EOL;
	        } // for

	        // display the last page
	        echo ' <span class="current_page">', $total_pages, '</span> ', EOL;

	    } else {
	        // somewhere in the middle
	        $current_page_round = round($current_page, -1); // round it out to find the actualy page

	        // display the first page
	        echo HTML::anchor($page->url(1), 1), EOL;

	        // when we are more than 2 pages from first one, display ...
	        if ($current_page - 3 > 2) {
        		echo $ellipsis;
	        }

	        // display the 10, 20, 30 ensuring we are not going over the number of pages
	        for ($i=$current_page_round - 30; $i<=$current_page_round - 10; $i+=10) {
	            if ($i > 1 && $i < ($total_pages - 1)) {
	                $new_offset = ($i-1) * $items_per_page;
	                echo HTML::anchor($page->url($i), $i), $ellipsis, EOL;
	            }
	        } // for

	        // display the 3 pages before and after the current page
	        for ($i = $current_page - 3; $i < $current_page + 4; $i++) {
	            if ($i > 1 && $i < ($total_pages)) {
	                if ( (($i -1) * $items_per_page) == $offset ) {
                		echo ' <span class="current_page"> ', $i, '</span> ', EOL;
	                } else {
	                    $new_offset = ($i-1) * $items_per_page;
	                    echo ' ', HTML::anchor($page->url($i), $i), ' ', EOL;
	                } // if
	            }
	        } // for

	        // display ... to separate the numbers in the middle and the ones at the end
	        if ($i < $total_pages) {
        		echo $ellipsis;
	        }

	        // display the 90, 100, 110 at the end
	        for ($i=$current_page_round + 10; $i<=$current_page_round + 30; $i+=10) {
	            if ($i > 1 && $i < ($total_pages - 1)) {
	                $new_offset = ($i-1) * $items_per_page;
	                echo HTML::anchor($page->url($i), $i), $ellipsis, EOL;
	            }
	        } // for

	        // display ...
	        if ($i < $total_pages) {
	            echo $ellipsis;
	        }

	        // display the last page
	        $new_offset = ($total_pages * $items_per_page) - $items_per_page;
	        echo HTML::anchor($page->url($total_pages), $total_pages), EOL;
	    } // if
	} // if

	if ($last_page !== FALSE) {
		echo HTML::anchor($page->url($last_page), __('Last'));
	} else {
		echo __('Last');
	}
	echo ' ';

	if ($next_page !== FALSE) {
		echo HTML::anchor($page->url($next_page), '' . __('Next') . '<span class="cl4_icon cl4_right"></span>', array('class' => 'next'));
	} else {
		echo '<span class="no_next">', __('Next'), '<span class="cl4_icon cl4_no_right"></span></span>';
	}
} // if

?>
	</div>
	<div class="cl4_nav_showing">Showing <?php echo intval($items_on_page); ?> of <?php echo intval($total_items); ?> items</div>
</div><!-- .cl4_nav -->