function makeUrl() {
    var xhr;

    if (window.XMLHttpRequest) {
        // create the XHML HTTP Request for IE7+, Firefox, Chrome, Opera, Safari
        xhr = new XMLHttpRequest();
    } else {
        // create the XHML HTTP Request for IE6, IE5
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
    }

    // when the request receives a response...
    xhr.onreadystatechange = function() {
      // if the request is complete
      if (xhr.readyState == XMLHttpRequest.DONE ) {
        // if the status is ok
        if(xhr.status == 200){
            // parse the JSON response
            var response = JSON.parse(xhr.responseText);
            // if the status of the response is OK
            if(parseInt(response.status) == 0)
              // create the short url 
              document.getElementById("result").innerHTML = window.location.origin+"/"+response.data;
            else
              // show the error messsage
              document.getElementById("result").innerHTML = response.message;
        }
        // if we get a 400
        else if(xhr.status == 400) {
           document.getElementById("result").innerHTML = "Error: 400";
        }
        // or some other http status
        else {
            document.getElementById("result").innerHTML = "Unkown Error";
        }
      }
    }

    // get the information fromt he field we want to parse
    var data = document.getElementById("url").value;

    // open up the current url, set the type to POST
    xhr.open("POST", "index.php", true);
    // make sure the header is form and url encoded
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    // send it off!
    xhr.send("url="+data);
}


