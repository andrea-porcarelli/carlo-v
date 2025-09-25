<style type="text/css">
    #map {
        height: 150px;
        width: 100%;
    }
    .pac-container{
        z-index: 10000;
    }
</style>
<div class="col-xs-12" style="margin-top: 15px;position:relative;">
    <label>
        Address
    </label>
    <input id="autocomplete" type="text" class="form-control"
        name="{{ $name }}"
        placeholder="Enter Address"
        @if(isset($hiddent))
            hidden
        @endif
        @if(isset($value))
            value="{{ $address ?? '' }}"
        @else
            value="{{ $object[$name] ?? '' }}"
        @endif/>
    <input id="latitude" name="latitude" type="text" hidden
        @if(isset($latitude))
            value="{{ $latitude ?? '' }}"
        @else
            value="{{ $object['latitude'] ?? '' }}"
        @endif
    />
    <input id="longitude" name="longitude" type="text" hidden
        @if(isset($longitude))
            value="{{ $longitude ?? '' }}"
        @else
            value="{{ $object['longitude'] ?? '' }}"
        @endif
   />

</div>
<div class="col-xs-12 m-t-sm">
    <div id="map"></div>
</div>
<script type="text/javascript">
    // console.log({{!empty($lat) ? $lat : 41.902782}});
    // console.log({{!empty($lng) ? $lng : 12.496366}});


    function initMap() {
        const myLatLng = { lat: {{!empty($lat) ? $lat : 41.902782}}, lng: {{!empty($lng) ? $lng : 12.496366}} };
        const map = new google.maps.Map(document.getElementById("map"), {
        zoom: 15,
        center: myLatLng,
        });

        new google.maps.Marker({
        position: myLatLng,
        map,
        title: "Hello Greenia!",
        });

        const input = document.getElementById("autocomplete");
        const latitude = document.getElementById("latitude");
        const longitude = document.getElementById("longitude");

        const options = {
            fields: ["formatted_address", "geometry", "name"],
            types: ["address"],
            componentRestrictions: { country: "it" },
        };
       var autocomplete = new google.maps.places.Autocomplete(input,options);

            // Listener for whenever input value changes
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                if(!place.geometry){
                    input.placeholder = "Enter Address";
                }else{
                  const map = new google.maps.Map(document.getElementById("map"), {
                  zoom: 15,
                  center: place.geometry.location,
                  });

                  new google.maps.Marker({
                  position: place.geometry.location,
                  map,
                  });
                   input.value = place.formatted_address;
                   latitude.value = place.geometry.location.lat();
                   longitude.value = place.geometry.location.lng();
                }


            });

    }

    window.initMap = initMap;
</script>

<script async
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAP_KEY') }}&libraries=places&callback=initMap" defer await>
</script>
