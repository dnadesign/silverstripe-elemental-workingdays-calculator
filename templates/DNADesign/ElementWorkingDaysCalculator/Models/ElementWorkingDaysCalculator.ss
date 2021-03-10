
<div class="working-days-calculator wdc">
    <% if $ShowTitle %>
        <h3 class="element__title">$Title</h3>
    <% end_if %>
    <% if $Introduction %>
        <div class="element__introduction">$Introduction</div>
    <% end_if %>
    $Controller.CalculatorForm
    <div class="wdc__results">
        <% include WorkingDaysCalculatorResults Results=$Controller.getResults() %>
    </div>
</div>

