{{-- One cell of the fitting coverage matrix.

     A null cell means the fitting is not offered for this age band, which is a normal
     state. A cell with figures is ready. A cell offered but empty blocks publishing. --}}
@if($cell === null)
    <span class="text-muted">&mdash;</span>
@elseif($cell['complete'])
    <span class="badge text-bg-success">Offered</span>
@else
    <span class="badge text-bg-warning">No figures</span>
@endif