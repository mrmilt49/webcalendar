<?php
/*
 * $Id$
 *
 * Page Description:
 *	Main page for install/config of db settings.
 *	This page is used to create/update includes/settings.php.
 *
 * Input Parameters:
 *	None
 *
 * Security:
 *	The first time this page is accessed, there are no security
 *	precautions.   The user is prompted to generate a config password.
 *	From then on, users must know this password to make any changes
 *	to the settings in settings.php./
 *
 */
include_once '../includes/php-dbi.php';

$file = "../includes/settings.php";

// Get value from POST form
function getPostValue ( $name ) {
  if ( ! empty ( $_POST[$name] ) )
    return $_POST[$name];
  if ( ! isset ( $HTTP_POST_VARS ) )
    return null;
  if ( ! isset ( $HTTP_POST_VARS[$name] ) )
    return null;
  return ( $HTTP_POST_VARS[$name] );
}


// Get value from GET form
function getGetValue ( $name ) {
  if ( ! empty ( $_GET[$name] ) )
    return $_GET[$name];
  if ( ! isset ( $HTTP_GET_VARS ) )
    return null;
  if ( ! isset ( $HTTP_GET_VARS[$name] ) )
    return null;
  return ( $HTTP_GET_VARS[$name] );
}



// First pass at settings.php.
// We need to read it first in case the install_disabled value is
// set to true.
$fd = @fopen ( $file, "rb", true );
$settings = array ();
$installDisabled = false;
if ( ! empty ( $fd ) ) {
  while ( ! feof ( $fd ) ) {
    $buffer = fgets ( $fd, 4096 );
    if ( preg_match ( "/^(\S+):\s*(.*)/", $buffer, $matches ) ) {
      if ( $matches[1] == "install_disabled" &&
        $matches[2] == "true" ) {
        $installDisabled = true;
      }
    }
  }
  fclose ( $fd );
}



// Is this a db connection test?
// If so, just test the connection, show the result and exit.
$action = getGetValue ( "action" );
if ( ! empty ( $action ) && $action == "dbtest" && ! $installDisabled ) {
  // TODO: restrict access here also...
  $db_type = getGetValue ( 'db_type' );
  $db_host = getGetValue ( 'db_host' );
  $db_database = getGetValue ( 'db_database' );
  $db_login = getGetValue ( 'db_login' );
  $db_password = getGetValue ( 'db_password' );

  echo "<html><head><title>WebCalendar: Db Connection Test</title>\n" .
    "</head><body style=\"background-color: #fff;\">\n";
  echo "<p><b>Connection Result:</b></p><blockquote>";

  $c = dbi_connect ( $db_host, $db_login,
    $db_password, $db_database );

  if ( $c ) {
    echo "<span style=\"color: #0f0;\">Success</span></blockquote>";
  } else {
    echo "<span style=\"color: #0f0;\">Failure</span</blockquote>";
    echo "<br/><br/><b>Reason:</b><blockquote>" . dbi_error () .
      "</blockquote>\n";
  }
  echo "<br/><br/><br/><div align=\"center\"><form><input align=\"middle\" type=\"button\" onclick=\"window.close()\" value=\"Close\" /></form></div>\n";
  echo "</p></body></html>\n";
  exit;
}




$exists = file_exists ( $file );
$canWrite = is_writable ( $file );



// If we are handling a form POST, then take that data and put it in settings
// array.
$x = getPostValue ( "form_db_type" );
$onload = "";
if ( empty ( $x ) ) {
  // No form was posted.  Set defaults if none set yet.
  if ( ! file_exists ( $file ) ) {
    $settings['db_type'] = 'mysql';
    $settings['db_host'] = 'localhost';
    $settings['db_database'] = 'intranet';
    $settings['db_login'] = 'webcalendar';
    $settings['db_password'] = 'webcal01';
    $settings['db_persistent'] = 'true';
    $settings['readonly'] = 'false';
    $settings['user_inc'] = 'user.php';
    $settings['install_disabled'] = 'false';
  }
} else {
  $settings['db_type'] = getPostValue ( 'form_db_type' );
  $settings['db_host'] = getPostValue ( 'form_db_host' );
  $settings['db_database'] = getPostValue ( 'form_db_database' );
  $settings['db_login'] = getPostValue ( 'form_db_login' );
  $settings['db_password'] = getPostValue ( 'form_db_password' );
  $settings['db_persistent'] = getPostValue ( 'form_db_persistent' );
  $settings['single_user_login'] = getPostValue ( 'form_single_user_login' );
  $settings['readonly'] = getPostValue ( 'form_readonly' );
  $settings['install_disabled'] = getPostValue ( 'form_install_disabled' );
  if ( $settings['install_disabled'] == 'true' )
    $installDisabled = true;
  if ( getPostValue ( "form_user_inc" ) == "http" ) {
    $settings['use_http_auth'] = 'true';
    $settings['single_user'] = 'false';
    $settings['user_inc'] = 'user.php';
  } else if ( getPostValue ( "form_user_inc" ) == "none" ) {
    $settings['use_http_auth'] = 'false';
    $settings['single_user'] = 'true';
    $settings['user_inc'] = 'user.php';
  } else {
    $settings['use_http_auth'] = 'false';
    $settings['single_user'] = 'false';
    $settings['user_inc'] = getPostValue ( 'form_user_inc' );
  }
  // Save settings to file now.
  $onload = "alert('Your settings have been saved.\\n\\nPlease be sure to disable this page.\\nOtherwise, any user will be able to\\nobtain your database login and password.\\n');";
  $fd = @fopen ( $file, "w+t", true );
  if ( empty ( $fd ) ) {
    if ( file_exists ( $fd ) ) {
      $onload = "alert('Error: unable to write to file $file\\nPlease change the file permissions of this file.');";
    } else {
      $onload = "alert('Error: unable to write to file $file\\nPlease change the file permissions of your includes directory\\nto allow writing by other users.');";
    }
  } else {
    fwrite ( $fd, "<?php\n" );
    fwrite ( $fd, "# updated via install/index.php on " . date("r") . "\n" );
    foreach ( $settings as $k => $v ) {
      fwrite ( $fd, $k . ": " . $v . "\n" );
    }
    fwrite ( $fd, "# end settings.php\n?>\n" );
    fclose ( $fd );
    // Change to read/write by us only (only applies if we created file)
    // This hides the db password from snoopy users.
    @chmod ( $file, 0600 );
  }
}


if ( ! $installDisabled ) {
  $fd = @fopen ( $file, "rb", true );
  if ( ! empty ( $fd ) ) {
    while ( ! feof ( $fd ) ) {
      $buffer = fgets ( $fd, 4096 );
      if ( preg_match ( "/^#/", $buffer ) )
        continue;
      if ( preg_match ( "/^<\?/", $buffer ) ) // start php code
        continue;
      if ( preg_match ( "/^\?>/", $buffer ) ) // end php code
        continue;
      if ( preg_match ( "/(\S+):\s*(.*)/", $buffer, $matches ) ) {
        $settings[$matches[1]] = $matches[2];
        //echo "settings $matches[1] => $matches[2] <br>";
      }
    }
    fclose ( $fd );
  }
}


// Attempt a db connection
$connectSuccess = false;
if ( ! $installDisabled ) {
  $db_type = $settings['db_type'];
  $c = @dbi_connect ( $settings['db_host'], $settings['db_login'],
    $settings['db_password'], $settings['db_database'] );
  if ( $c ) {
    $connectSuccess = true;
  }
}


?>
<html>
<head><title>WebCalendar Database Setup</title>
<?php include "../includes/js/visible.php"; ?>
<script language="JavaScript">

function testSettings () {
  var url;
  var form = document.dbform;
  url = "index.php?action=dbtest" +
    "&db_type=" + form.form_db_type.value +
    "&db_host=" + form.form_db_host.value +
    "&db_database=" + form.form_db_database.value +
    "&db_login=" + form.form_db_login.value +
    "&db_password=" + form.form_db_password.value;
  //alert ( "URL:\n" + url );
  window.open ( url, "wcDbTest", "width=400,height=350,resizable=yes,scrollbars=yes" );
}
function validate(form)
{
  var form = document.dbform;
  // only check is to make sure single-user login is specified if
  // in single-user mode
  if ( form.form_user_inc.options[4].selected ) {
    if ( form.form_single_user_login.value.length == 0 ) {
      // No single user login specified
      alert ( "Error: you must specify a\nSingle-User Login" );
      form.form_single_user_login.focus ();
      return false;
    }
  }
  // Submit form...
  form.submit ();
}
function auth_handler () {
  var form = document.dbform;
  if ( form.form_user_inc.options[4].selected ) {
    makeVisible ( "singleuser" );
  } else {
    makeInvisible ( "singleuser" );
  }
}

function disablePage () {
  if ( confirm ( "Are you sure you want to disable\naccess to this page?\n\n" +
    "If you need to change database settings\n" +
    "in the future, you will need\n" +
    "to edit the settings.php by hand and\n" +
    "change the value of the install_disabled\n" +
    "parameter to be 'false'.\n" ) ) {
    document.dbform.form_install_disabled.value = 'true';
    document.dbform.submit ();
    return true;
  }
  return false;
}
</script>
<style type="text/css">
body {
  background-color: #ffffff;
  font-family: Arial, Helvetica, sans-serif;
  margin: 0;
}
table {
  border: 1px solid #ccc;
}
th.header {
  font-size: 18px;
  background-color: #eee;
}
td {
  padding: 5px;
}
td.prompt {
  font-weight: bold;
  padding-right: 20px;
}
div.nav {
  margin: 0;
  border-bottom: 1px solid #000;
}
div.main {
  margin: 10px;
}
li {
  margin-top: 10px;
}
</style>
</head>
<body onload="auth_handler(); <?php echo $onload;?>">
<?php
/* other features coming soon.... 
<div class="nav">
<table border="0" width="100%">
<tr>
<td>&lt;&lt;<b>Database Setup</b>&gt;&gt;</td>
<td>&lt;&lt;<a href="setup.php">Setup Wizard</a>&gt;&gt;</td>
<td>&lt;&lt;<a href="diag.php">Diagnostics</a>&gt;&gt;</td>
</tr></table>
</div>
*/
?>
<div class="main">
<h2>WebCalendar Database Setup</h2>

<p>Current Status:</p>
<ul>

<?php if ( ! $installDisabled ) { ?>
  <?php if ( $connectSuccess ) { ?>
  <li> Your current database settings are able to
  access the database.</li>
  <?php } else { ?>
  <li> Your current database settings are <b>not</b> able to
  access the database.</li>
  <?php } ?>
<?php } ?>


<?php if ( $installDisabled ) { ?>
<li><b>This page has been disabled.</b></li>
<?php } else if ( $exists && ! $canWrite ) { ?>
<li><b>Error:</b>
The file permissions of <tt>settings.php</tt> are set so
that this script does not have permission to write changes to it.
You must change the file permissions of <tt>settings.php</tt>
to use this script.
</li>

<?php } else { ?>

  <?php if ( ! $exists ) { ?>
  <li>You have not created a <tt>settings.php</tt> file yet.</li>
  <?php } ?>

<?php if ( empty ( $PHP_AUTH_USER ) ) { ?>
<li>HTTP-based authentication was not detected.
You will need to reconfigure your web server if you wish to
select "Web Server" from the "User Authentication" choices below.
</li>
<?php } else { ?>
<li>HTTP-based authentication was detected.
User authentication is being handled by your web server.
You should select "Web Server" from the list of
"User Authentication " choices below.
</li>
<?php } ?>

</ul>

<form action="index.php" method="POST" name="dbform">

<table>
<tr><th class="header" colspan="2">Database Settings</th></tr>

<tr><td class="prompt">Database Type:</td>
<td>
<select name="form_db_type">
<?php
  echo "<option value=\"mysql\" " .
    ( $settings['db_type'] == 'mysql' ? " selected=\"selected\"" : "" ) .
    "> MySQL </option>\n";

  echo "<option value=\"oracle\" " .
    ( $settings['db_type'] == 'oracle' ? " selected=\"selected\"" : "" ) .
    "> Oracle (OCI) </option>\n";

  echo "<option value=\"postgresql\" " .
    ( $settings['db_type'] == 'postgresql' ? " selected=\"selected\"" : "" ) .
    "> PostgreSQL </option>\n";

  echo "<option value=\"odbc\" " .
    ( $settings['db_type'] == 'odbc' ? " selected=\"selected\"" : "" ) .
    "> ODBC </option>\n";

  echo "<option value=\"ibase\" " .
    ( $settings['db_type'] == 'ibase' ? " selected=\"selected\"" : "" ) .
    "> Interbase </option>\n";
?>
</select>
</td></tr>

<tr><td class="prompt">Server:</td>
<td><input name="form_db_host" size="20" value="<?php echo $settings['db_host'];?>" /></td></tr>

<tr><td class="prompt">Database Name:</td>
<td><input name="form_db_database" size="20" value="<?php echo $settings['db_database'];?>" /></td></tr>

<tr><td class="prompt">Login:</td>
<td><input name="form_db_login" size="20" value="<?php echo $settings['db_login'];?>" /></td></tr>

<tr><td class="prompt">Password:</td>
<td><input name="form_db_password" size="20" value="<?php echo $settings['db_password'];?>" /></td></tr>

<tr><td class="prompt">Connection Persistence:</td>
<td><input name="form_db_persistent" value="true" type="radio"
  <?php if ( $settings['db_persistent'] == 'true' )
          echo " checked=\"checked\"";
  ?> >Enabled
  &nbsp;&nbsp;&nbsp;&nbsp;
  <input name="form_db_persistent" value="false" type="radio"
  <?php if ( $settings['db_persistent'] != 'true' )
          echo " checked=\"checked\"";
  ?> >Disabled
  </td></tr>

<tr><td colspan="2" align="center">
<input name="action" type="button" value="Test Settings"
  onclick="testSettings()" />
</td></tr>

</table>

<br/><br/>

<table>
<tr><th class="header" colspan="2">Application Settings</th></tr>

<tr><td class="prompt">User Authentication:</td>
<td>
<select name="form_user_inc" onchange="auth_handler()">
<?php
  echo "<option value=\"user.php\" " .
    ( $settings['user_inc'] == 'user.php' && $settings['use_http_auth'] != 'true' ? " selected=\"selected\"" : "" ) .
    "> Web-based via WebCalendar (default) </option>\n";

  echo "<option value=\"http\" " .
    ( $settings['user_inc'] == 'user.php' && $settings['use_http_auth'] == 'true' ? " selected=\"selected\"" : "" ) .
    "> Web Server " .
    ( empty ( $PHP_AUTH_USER ) ? "(not detected)" : "(not detected)" ) .
    "</option>\n";

  echo "<option value=\"user-ldap.php\" " .
    ( $settings['user_inc'] == 'user-ldap.php' ? " selected=\"selected\"" : "" ) .
    "> LDAP </option>\n";

  echo "<option value=\"user-nis.php\" " .
    ( $settings['user_inc'] == 'user-nis.php' ? " selected=\"selected\"" : "" ) .
    "> NIS </option>\n";

  echo "<option value=\"none\" " .
    ( $settings['user_inc'] == 'user.php' && $settings['single_user'] == 'true' ? " selected=\"selected\"" : "" ) .
    "> None (Single-User) </option>\n";
?>
</td></tr>

<tr id="singleuser"><td class="prompt">&nbsp;&nbsp;&nbsp;Single-User Login:</td>
<td><input name="form_single_user_login" size="20" value="<?php echo $settings['single_user_login'];?>" /></td></tr>

<tr><td class="prompt">Read-Only:</td>
<td><input name="form_readonly" value="true" type="radio"
  <?php if ( $settings['readonly'] == 'true' )
          echo " checked=\"checked\"";
  ?> >Yes
  &nbsp;&nbsp;&nbsp;&nbsp;
  <input name="form_readonly" value="false" type="radio"
  <?php if ( $settings['readonly'] != 'true' )
          echo " checked=\"checked\"";
  ?> >No
  </td></tr>

</table>

<br />
<br />

<input name="action" type="button" value="Save Settings"
  onclick="return validate();" />
<?php if ( file_exists ( $file ) ) { ?>
<input type="button" value="Disable This Page" onclick="return disablePage();" />
<?php } ?>
<input type="hidden" name="form_install_disabled" value="false" />
</form>

<?php } ?>
</div>
</body>
</html>
