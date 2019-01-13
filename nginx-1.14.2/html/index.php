<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- tenorflow.js -->
    <script src="/js/tf.min.js"></script>
    <!-- jquery -->
    <script src="/js/jquery.min.js"></script>    
    <!-- knockout -->
    <script src="/js/knockout-min.js"></script>
    <script src="/js/knockout.mapping.min.js"></script>
    <!-- processing.js -->
    <script src="/js/processing.min.js"></script>
    <script src="/js/p5.min.js"></script>
    <script src="/js/p5.dom.min.js"></script>
    <!-- amplify.js -->
    <script src="/js/amplify.min.js"></script>
    <script src="/js/amplify.request.min.js"></script>
    <script src="/js/amplify.store.min.js"></script>
    <!-- dexie.js -->
    <script src="/js/dexie.min.js"></script>
    <!-- boostrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">    
    <script src="/js/bootstrap.min.js" ></script>    
    <!-- spin.js -->
    <link rel="stylesheet" href="/css/spin.css" />    
    <script src="/js/spin.js"></script>
    <!-- c3 and d3 -->
    <link rel="stylesheet" href="/css/c3.min.css" />    
    <script src="/js/d3.min.js"></script>
    <script src="/js/c3.min.js"></script>
    <!-- pnotify -->
    <link rel="stylesheet" href="/css/pnotify.css" />    
    <link rel="stylesheet" href="/css/pnotify.brighttheme.css" />        
    <script src="/js/pnotify.js"></script>    
    <!-- our source -->
    <script src="/js/viewmodel/viewmodel.js"></script>  
    <script src="/js/functions/tf_funcs.js"></script>
    <script src="/js/functions/predictions_funcs.js"></script>
    <script src="/js/viewmodel/spin.js"></script>    
    <script src="/js/viewmodel/notify.js"></script>
    <script src="/js/model/local_db.js"></script>
    <link rel="stylesheet" href="/css/app.css" />
    <title>MNIST using Tensorflow.js</title>
    
</head>
<body id="vm">
    
    <div class="container-fluid" style="margin-top: 4em">
        <div class="row" style="margin-top: 1em">
            <div class="col-1">
            <canvas id="canvas3"></canvas>
            <!-- <img src='image.php?imgid=12345' alt='this is your img from the database' /> -->                
            </div>
            <div class="col-7 ml-auto d-flex align-items-center button-row" align="center">
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.load_data,                                    enable: load_data_button_enabled()">1) Load Data</button>
                <select class="custom-select" style="width: 10em" data-bind="options: model_types, value: selected_model_type,      enable: create_tf_model_button_enabled()"></select>
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.create_tf_model,                              enable: create_tf_model_button_enabled()">2) Create TF Model</button>                
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.train_model,                                  enable: train_button_enabled()">3) Train</button>
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.save_model,                                   enable: save_model_button_enabled()">4) Save Model</button>
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.predict,                                      enable: save_model_button_enabled()">5) Predict</button>
            </div>
            <div class="col-3">
                <table class="table">
                    <tr>
                        <td colspan="2"><div class="spinner"> <div id="spinner"></div> </div></td>                        
                    </tr>                    
                    <tr>
                        <td>Done %</td>
                        <td data-bind="text: percent_training_complete"></td>
                    </tr>
                    <tr>
                        <td>Accuracy %</td>
                        <td data-bind="text: current_accuracy"></td>
                    </tr>
                    <tr>
                        <td>Val Accuracy %</td>
                        <td data-bind="text: current_validation_accuracy"></td>
                    </tr>
                    <tr>
                        <td>Loss</td>
                        <td data-bind="text: current_loss"></td>
                    </tr>
                    <tr>
                        <td>Batch #</td>
                        <td data-bind="text: current_batch_num"></td>
                    </tr>
                    <tr>
                        <td>Epoch #</td>
                        <td data-bind="text: current_epoch"></td>
                    </tr>
                </table>                
            </div>         
            <div class="col-1">
            </div>                           
        </div>
        <div class="row" style="height:300px" data-bind="visible: is_training">
            <div class="col-2">
                <h1>Training</h1>
            </div>
            <div class="col-4">
                <div id="accuracy_chart">
                </div>
            </div>
            <div class="col-4">
                <div id="loss_chart">
                </div>
            </div>
            <div class="col-2">
            </div>
        </div>          
        <div class="row" data-bind="visible: is_training">
            <div class="col-2 ml-auto d-flex align-items-center">
                <h1>Test Predictions</h1>
            </div>
            <div class="col-8">
                <div id="images" style="display:inline-flex"></div>
            </div>
            <div class="col-2">
            </div>            
        </div>            
    </div>
    <script>

const IMAGE_H = 28;
const IMAGE_W = 28;
const IMAGE_SIZE = IMAGE_H * IMAGE_W;
const NUM_CLASSES = 10;
const NUM_DATASET_ELEMENTS = 35700 + 6300;
const NUM_TRAIN_ELEMENTS = 35700 ;
const NUM_TEST_ELEMENTS = NUM_DATASET_ELEMENTS - NUM_TRAIN_ELEMENTS;


// 55/65 = .15
// const NUM_DATASET_ELEMENTS = 65000;
// const NUM_TRAIN_ELEMENTS = 55000;
// const NUM_TEST_ELEMENTS = NUM_DATASET_ELEMENTS - NUM_TRAIN_ELEMENTS;

    //Dexie.delete('localmnist');
    //amplify.store( 'data_loaded',null)

    //var img;
    var vm = new ViewModel();
    ko.applyBindings(vm, document.getElementById('vm'));
    //vm.load_data();





//TODO:   processing.js functions
$(document).ready(function() {
});

    </script>
</body>
</html>