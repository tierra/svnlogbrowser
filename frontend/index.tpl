<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
  <title>{TYPE} SVN Changelog</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
  <link rel="stylesheet" type="text/css" href="{STYLESHEET}" />
  <link id="devsort_alpha" rel="stylesheet" href="devsort_alpha.css" />
  <link id="hide_inactive" rel="stylesheet" href="hide_inactive.css" />
  <script type="text/javascript">
	<!--
	function showChanges(revision) {
		var request;
		try {
			// Opera 8.0+, Firefox, Safari
			request = new XMLHttpRequest();
		} catch (e) {
			// Internet Explorer Browsers
			try{
				request = new ActiveXObject("Msxml2.XMLHTTP");
			} catch (e) {
				try{
					request = new ActiveXObject("Microsoft.XMLHTTP");
				} catch (e) {
					// Something went wrong
					alert("Your browser doesn't support this, sorry. Please turn off file summaries to view these changes.");
					return false;
				}
			}
		}
		request.onreadystatechange = function() {
			if(request.readyState == 4) {
				id = "csi" + revision;
				element = document.getElementById(id);
				element.innerHTML = request.responseText;
			}
		}
		request.open("GET", "changes.php?t={TYPENUM}&r=" + revision, true);
		request.send(null);
	}
	-->
  </script>
  <script type="text/javascript">
    function enableStyle(id, enabled) {
      var sheet = document.getElementById(id);
      if(sheet) sheet.disabled = (!enabled); }
    function toggleStyle(id) {
      var sheet = document.getElementById(id);
      if(sheet) sheet.disabled = (!(sheet.disabled)); }
  </script>
</head>
<body>
  <div class="header">
    <h1>{TYPE} SVN Changelog</h1>
  </div>
  
<div class="content">

<div class="clnavbox">
  <h3>Select Changelog:</h3>
  <p>{TYPES}</p>
  <h3>Select page:</h3>
  <p>{PAGENAV}</p>
  <h3>Changes per page:</h3>
  <p>{CHANGES}</p>
  <h3>Summarize files:</h3>
  <p>{SUMMARIZE}</p>
  <h3>Search:</h3>
  <div id="search"><form method="get" action="">
    {SEARCHQV}
    <table border="0" cellpadding="0" cellspacing="0">
      <tr><td colspan="2">{SEARCHRANGE}</td></tr>
      <tr><td nowrap="nowrap"><input type="text" name="q" id="q" size="16" maxlength="128" value="{SEARCHQUERY}"/></td>
          <td style="text-align: right; width: 20px;" valign="middle"><input type="image" src="images/magnifier.png" name="qs" value=""/></td></tr>
    </table>
  </form></div>
  <h3>Filter by Developer:</h3>
  <div style="text-align: center;">
    <a href="javascript:;" onclick="enableStyle('devsort_alpha', false); return false;">
      <img src="images/font.png" style="padding: 0px 4px;" alt="Sort Alphabetically" title="Sort Alphabetically" /></a>
    <a href="javascript:;" onclick="enableStyle('devsort_alpha', true); return false;">
      <img src="images/chart_bar.png" style="padding: 0px 4px;" alt="Sort by Commits" title="Sort by Commits" /></a>
    <a href="javascript:;" onclick="toggleStyle('hide_inactive'); return false;">
      <img src="images/status_away.png" style="padding: 0px 4px;" alt="Show/Hide Inactive Developers" title="Show/Hide Inactive Developers" /></a>
  </div>
  {DEVCONTROL}
</div>

<p>This changelog reflects changes in the {TYPE} SVN repository, and is
updated once every hour.  All reported times are in UTC.</p>

<div style="margin: 15px 200px 15px 20px; text-align: center;"><div class="legend"><ul>
  <li>Legend:</li>
  <li><img src="images/{ICON_A}" alt="Added (A)" /> <span class="A">Added (A)</span></li>
  <li><img src="images/{ICON_D}" alt="Deleted (D)" /> <span class="D">Deleted (D)</span></li>
  <li><img src="images/{ICON_M}" alt="Modified (M)" /> <span class="M">Modified (M)</span></li>
  <li><img src="images/{ICON_R}" alt="Copied (R)" /> <span class="R">Copied (R)</span></li>
  <li><span class="branch">[Branch]</span></li>
  <li><span class="tag">[Tag]</span></li>
</ul></div></div>

{FILTERS}

<div class="changelog">
<h2>Changelog for {TYPE} ({LINECOUNT} revisions):</h2>
{CHANGELOG}
  <div class="pagenavbottom">
    <h2>Select page:</h2>
    <p>{PAGENAV}</p>
  </div>
</div>

</div>

<div id="footer"><p>
  Icons &copy; 2007 <a href="http://www.famfamfam.com/lab/icons/silk/">Mark James</a> under the
  <a href="http://creativecommons.org/licenses/by/2.5/">CC Attribution 2.5 License</a>
  <br />Powered by <a href="http://svnlogbrowser.org/">svnLogBrowser</a>
</p></div>

<!-- Page generation time: {PROCESSTIME} seconds -->

</body>
</html>
