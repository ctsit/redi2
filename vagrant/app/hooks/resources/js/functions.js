/**
 * Created by kenbergquist on 7/16/15.
 */

function gotoNextEvent(ob, redirectURL) {
    if ($(ob).attr('name') == 'submit-btn-savenextevent') {
        var redirectInput = "<input type=\"hidden\" name=\"save-and-redirect\" id=\"save-and-redirect\" >";
        $('form#form').append(redirectInput);
        $('input#save-and-redirect').attr('value', redirectURL);
        $(ob).attr('name', 'submit-btn-saverecord');
    }
    dataEntrySubmit(ob);
}
