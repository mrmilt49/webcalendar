<?php
include_once 'includes/init.php';

$USERS_PER_TABLE = 6;

if ( $allow_view_other == "N" && ! $is_admin ) {
  // not allowed...
  do_redirect ( "$STARTVIEW.php" );
}

if ( empty ( $friendly ) )
  $friendly = 0;

// Find view name in $views[]
$view_name = "";
for ( $i = 0; $i < count ( $views ); $i++ ) {
  if ( $views[$i]['cal_view_id'] == $id ) {
    $view_name = $views[$i]['cal_name'];
  }
}

$INC = array('js/popups.php');
print_header($INC);

set_today($date);

$next = mktime ( 3, 0, 0, $thismonth, $thisday + 7, $thisyear );
$nextyear = date ( "Y", $next );
$nextmonth = date ( "m", $next );
$nextday = date ( "d", $next );
$nextdate = sprintf ( "%04d%02d%02d", $nextyear, $nextmonth, $nextday );

$prev = mktime ( 3, 0, 0, $thismonth, $thisday - 7, $thisyear );
$prevyear = date ( "Y", $prev );
$prevmonth = date ( "m", $prev );
$prevday = date ( "d", $prev );
$prevdate = sprintf ( "%04d%02d%02d", $prevyear, $prevmonth, $prevday );

// We add 2 hours on to the time so that the switch to DST doesn't
// throw us off.  So, all our dates are 2AM for that day.
if ( $WEEK_START == 1 )
  $wkstart = get_monday_before ( $thisyear, $thismonth, $thisday );
else
  $wkstart = get_sunday_before ( $thisyear, $thismonth, $thisday );
$wkend = $wkstart + ( 3600 * 24 * 6 );
$startdate = date ( "Ymd", $wkstart );
$enddate = date ( "Ymd", $wkend );

$thisdate = $startdate;

for ( $i = 0; $i < 7; $i++ ) {
  $days[$i] = $wkstart + ( 24 * 3600 ) * $i;
  $weekdays[$i] = weekday_short_name ( ( $i + $WEEK_START ) % 7 );
  $header[$i] = $weekdays[$i] . "<br />" .
     month_short_name ( date ( "m", $days[$i] ) - 1 ) .
     " " . date ( "d", $days[$i] );
}

?>

<table border="0" width="100%">
<tr><td style="text-align:left;">
<?php if ( ! $friendly ) { ?>
<a href="view_w.php?id=<?php echo $id?>&date=<?php echo $prevdate?>"><img src="leftarrow.gif" width="36" height="32" border="0" alt="<?php etranslate("Previous")?>" /></a>
<?php } ?>
</td>
<td style="text-align:center; color:<?php echo $H2COLOR?>;">
<font size="+2">
<b>
<?php
  echo date_to_str ( date ( "Ymd", $wkstart ), false ) .
    "&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;" .
    date_to_str ( date ( "Ymd", $wkend ), false );
?>
</b></font><br />
<?php echo $view_name ?>
</td>
<td style="text-align:right;">
<?php if ( ! $friendly ) { ?>
<a href="view_w.php?id=<?php echo $id?>&date=<?php echo $nextdate?>"><img src="rightarrow.gif" width="36" height="32" border="0" alt="<?php etranslate("Next")?>" /></a>
<?php } ?>
</td></tr>
</table>

<?php
// The table has names across the top and dates for rows.  Since we need
// to spit out an entire row before we can move to the next date, we'll
// save up all the HTML for each cell and then print it out when we're
// done....
// Additionally, we only want to put at most 6 users in one table since
// any more than that doesn't really fit in the page.

// get users in this view
$res = dbi_query (
  "SELECT cal_login FROM webcal_view_user WHERE cal_view_id = $id" );
$viewusers = array ();
if ( $res ) {
  while ( $row = dbi_fetch_row ( $res ) ) {
    $viewusers[] = $row[0];
  }
  dbi_free_result ( $res );
}
$e_save = array ();
$re_save = array ();
for ( $i = 0; $i < count ( $viewusers ); $i++ ) {
  /* Pre-Load the repeated events for quckier access */
  $repeated_events = read_repeated_events ( $viewusers[$i] );
  $re_save[$i] = $repeated_events;
  /* Pre-load the non-repeating events for quicker access */
  $events = read_events ( $viewusers[$i], $startdate, $enddate );
  $e_save[$i] = $events;
}


for ( $j = 0; $j < count ( $viewusers ); $j += $USERS_PER_TABLE ) {
  // since print_date_entries is rather stupid, we can swap the event data
  // around for users by changing what $events points to.

  // Calculate width of columns in this table.
  $num_left = count ( $viewusers ) - $j;
  if ( $num_left > $USERS_PER_TABLE )
    $num_left = $USERS_PER_TABLE;
  if ( $num_left > 0 ) {
    if ( $num_left < $USERS_PER_TABLE ) {
      $tdw = (int) ( 90 / $num_left );
    } else {
      $tdw = (int) ( 90 / $USERS_PER_TABLE );
    }
  } else {
    $tdw = 5;
  }

?>

<table border="1" rules="all" width="100%" cellspacing="0" cellpadding="0" style="border-color: <?php echo $TABLEBG;?>;">

<tr><td width="10%" style="width:10%; background-color:<?php echo $THBG?>;">&nbsp;</td>

<?php

  // $j points to start of this table/row
  // $k is counter starting at 0
  // $i starts at table start and goes until end of this table/row.
  for ( $i = $j, $k = 0;
    $i < count ( $viewusers ) && $k < $USERS_PER_TABLE; $i++, $k++ ) {
    $user = $viewusers[$i];
    user_load_variables ( $user, "temp" );
    echo "<th class=\"tableheader\" style=\"width:$tdw%; background-color:$THBG;\">$tempfullname</td>";
  }
  echo "</tr>\n";
  
  
  for ( $xdate = $wkstart, $h = 0;
    date ( "Ymd", $xdate ) <= date ( "Ymd", $wkend );
    $xdate += ( 24 * 3600 ), $h++ ) {
    $wday = strftime ( "%w", $xdate );
    $weekday = weekday_short_name ( $wday );
    if ( date ( "Ymd", $xdate ) == date ( "Ymd", $today ) ) {
      $color = $TODAYCELLBG;
      $class = "tableheadertoday";
    } else {
      if ( $wday == 0 || $wday == 6 )
        $color = $WEEKENDBG;
      else
        $color = $CELLBG;
      $class = "tableheader";
    }
    echo "<tr><th width=\"10%\" class=\"$class\" style=\"width:10%; background-color:$color; vertical-align:top;\">" .
      "<font size=\"-1\" class=\"$class\">" . $weekday . " " .
      round ( date ( "d", $xdate ) ) . "</font></th>\n";
    for ( $i = $j, $k = 0;
      $i < count ( $viewusers ) && $k < $USERS_PER_TABLE; $i++, $k++ ) {
      $user = $viewusers[$i];
      $events = $e_save[$i];
      $repeated_events = $re_save[$i];
      echo "<td style=\"width:$tdw%; background-color:$color;\">";
      //echo date ( "D, m-d-Y H:i:s", $xdate ) . "<BR>";
      if ( empty ( $add_link_in_views ) || $add_link_in_views != "N" &&
        empty ( $friendly ) )
        echo html_for_add_icon ( date ( "Ymd", $xdate ), "", "", $user );
      print_date_entries ( date ( "Ymd", $xdate ),
        $user, $friendly, true );
      echo "</td>";
    }
    echo "</tr>\n";
  }

  echo "</table>\n<br /><br />\n";
}


$user = ""; // reset

if ( empty ( $friendly ) )
  echo $eventinfo;

if ( ! $friendly )
  echo "<a class=\"navlinks\" href=\"view_w.php?id=$id&date=$date&friendly=1\" " .
    "target=\"cal_printer_friendly\" onmouseover=\"window.status='" .
    translate("Generate printer-friendly version") .
    "'\">[" . translate("Printer Friendly") . "]</a>\n";


print_trailer ();
?>

</body>
</html>
