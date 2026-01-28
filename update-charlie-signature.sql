-- Update Charlie's signature to match Marcy's format for testing
UPDATE sync_state SET value = '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="3" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Calibri, sans-serif; font-size: 11pt;">
<img src="cid:charlie-logo" width="623" height="79" style="width: 623px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Calibri, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Calibri, sans-serif; font-size: 11pt;">
<span style="font-family: ''Arial Narrow'', sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Mobile</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 312.882.0826<br>
<i>Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: ''Arial Narrow'', sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:charlie@veerless.com" style="color: blue;">charlie@veerless.com</a></u></span><span style="font-family: ''Arial Narrow'', sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: ''Arial Narrow'', sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Calibri, sans-serif; font-size: 11pt;">
<br>
<span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.linkedin.com/in/charlieveerless" style="color: blue;"><img src="cid:charlie-linkedin" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i>&nbsp;</i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.instagram.com/meetveerless" style="color: blue;"><img src="cid:charlie-instagram" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i>&nbsp;</i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.twitter.com/charlieveerless" style="color: blue;"><img src="cid:charlie-twitter" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span>
</p>
</td>
</tr>
</tbody>
</table>', updated_at = NOW() WHERE name = 'signature_charlie';
