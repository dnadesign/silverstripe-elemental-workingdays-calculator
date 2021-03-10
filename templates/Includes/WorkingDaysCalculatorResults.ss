<% if $Results.Count > 0 %>
    <% loop $Results %>
        <div class="wdc__result">
            <% if $Interval.ResultIntroduction %>
                <p class="wdc__title">$Interval.ResultIntroduction</p>
            <% end_if %>
            <p class="wdc__date">$Date.Format('dd/MM/yyyy')</p>
            <% if $Holidays %>
                <p class="wdc__warning">NOTE: this date takes into consideration the following non-working days:</p>
                <ul class="wdc__holidayslist">
                    <% loop $Holidays %>
                        <li>$Title ($Date.Format('dd/MM/yyyy'))</li>
                    <% end_loop %>
                </ul>
            <% end_if %>
        </div>
    <% end_loop %>
<% end_if %>