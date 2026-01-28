-- Insert default email signatures for all users
-- Signatures use CID (Content-ID) references for inline images
-- Images are stored in /public/signatures/ and referenced as: {username}-logo, {username}-linkedin, etc.

-- Marcy's signature (based on actual email example)
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_marcy',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="3" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<img src="cid:marcy-logo" width="625" height="79" style="width: 625px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Mobile</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 312.882.0826<br>
<i>Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:marcy@veerless.com" style="color: blue;">marcy@veerless.com</a></u></span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<br>
<span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.linkedin.com/in/marcytwete" style="color: blue;"><img src="cid:marcy-linkedin" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i>&nbsp;</i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.instagram.com/meetveerless" style="color: blue;"><img src="cid:marcy-instagram" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span>
</p>
</td>
</tr>
</tbody>
</table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();

-- Charlie's signature (template - customize as needed)
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_charlie',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="3" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<img src="cid:charlie-logo" width="342" height="79" style="width: 342px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Mobile</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 612.384.7671<br>
<i>Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:charlie@veerless.com" style="color: blue;">charlie@veerless.com</a></u></span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<br>
<span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.linkedin.com/in/charlieveerless" style="color: blue;"><img src="cid:charlie-linkedin" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i>&nbsp;</i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.instagram.com/meetveerless" style="color: blue;"><img src="cid:charlie-instagram" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span>
</p>
</td>
</tr>
</tbody>
</table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();

-- Ann's signature (template - customize as needed)
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_ann',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="3" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<img src="cid:ann-logo" width="625" height="79" style="width: 625px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Mobile</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 801.949.3111<br>
<i>Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:ann@veerless.com" style="color: blue;">ann@veerless.com</a></u></span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<br>
<span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.linkedin.com/in/annveerless" style="color: blue;"><img src="cid:ann-linkedin" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i>&nbsp;</i></span><span style="font-family: Helvetica, sans-serif; font-size: 9pt;"><i><u><a href="http://www.instagram.com/meetveerless" style="color: blue;"><img src="cid:ann-instagram" width="40" height="40" style="width: 40px; height: 40px;"></a></u></i></span>
</p>
</td>
</tr>
</tbody>
</table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();

-- Kristen's signature
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_kristen',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="2" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<img src="cid:kristen-logo" width="342" height="79" style="width: 342px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Mobile</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 701.799.7361<br>
<i>Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:kristen@veerless.com" style="color: blue;">kristen@veerless.com</a></u></span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
</tr>
</tbody>
</table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();

-- Katie's signature
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_katie',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="2" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<img src="cid:katie-logo" width="342" height="79" style="width: 342px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:katie@veerless.com" style="color: blue;">katie@veerless.com</a></u></span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
</tr>
</tbody>
</table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();

-- Tameka's signature
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_tameka',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
<tbody>
<tr>
<td colspan="2" style="padding: 0in 5.4pt; vertical-align: top;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<img src="cid:tameka-logo" width="342" height="79" style="width: 342px; height: 79px;">
</p>
</td>
</tr>
<tr>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 13.25pt;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">&nbsp;</p>
</td>
<td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
<p style="margin: 0in; font-family: Aptos, sans-serif; font-size: 11pt;">
<span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><i><br>
Mobile</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 612.275.6053<br>
<i>Email</i>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="mailto:tameka@veerless.com" style="color: blue;">tameka@veerless.com</a></u></span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: rgb(59, 56, 56);"><br>
<i>Website</i>: &nbsp;&nbsp;&nbsp; </span><span style="font-family: Aptos, sans-serif; font-size: 10pt; color: blue;"><u><a href="http://www.veerless.com/" style="color: blue;">www.veerless.com</a></u></span>
</p>
</td>
</tr>
</tbody>
</table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();
