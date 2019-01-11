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
    <!-- our source -->
    <script src="/js/tf_funcs.js"></script>
    <link rel="stylesheet" href="/css/app.css" />
    <title>MNIST using Tensorflow.js</title>
    
</head>
<body id="vm">
    
    <div class="container-fluid" style="margin-top: 4em">
        <div class="row">
            <div class="col-3">
            </div>
            <div class="col-6" align="center">
                <button type="button" class="btn btn-primary" data-bind="click: $root.load_data,                                    enable: load_data_button_enabled()">1) Load Data</button>
                <select class="custom-select" style="width: 10em" data-bind="options: model_types, value: selected_model_type,      enable: create_tf_model_button_enabled()"></select>
                <button type="button" class="btn btn-primary" data-bind="click: $root.create_tf_model,                              enable: create_tf_model_button_enabled()">2) Create TF Model</button>                
                <button type="button" class="btn btn-primary" data-bind="click: $root.train_model,                                  enable: train_button_enabled()">3) Train</button>
            </div>
            <div class="col-3">
            </div>            
        </div>
        <div class="row" >
            <div class="col-12 spinner" align="center">                
                <div id="spinner">                    
                </div>            
            </div>        
        </div>        
        <div class="row">
            <div class="col-6">
                <div id="p5container">
                </div>
            </div>
            <div class="col-6">                    
            </div>
        </div>
        <div class="row">
            <div class="col-2">
            </div>
            <div class="col-8">
                <div id="images" style="display:inline-flex"></div>
            </div>
            <div class="col-2">
            </div>            
        </div>            
    </div>
    <script>


let data;
const IMAGE_H = 28;
const IMAGE_W = 28;
const IMAGE_SIZE = IMAGE_H * IMAGE_W;
const NUM_CLASSES = 10;
const NUM_DATASET_ELEMENTS = 33600 + 8400;
const NUM_TRAIN_ELEMENTS = 33600 ;

const NUM_TEST_ELEMENTS = NUM_DATASET_ELEMENTS - NUM_TRAIN_ELEMENTS;

    var ViewModel = function () {
        var self = this;        

        self.tf_model           = ko.observable({});

        self.data_loaded        =  ko.observable(false);
        self.data_is_loading    =  ko.observable(false);
        self.model_created      =  ko.observable(false);
        self.is_training        =  ko.observable(false);
        self.fetching           =  ko.observable(false);

        self.selected_model_type    =  ko.observable('ConvNet');
        self.model_types            =  ko.observableArray(['ConvNet', 'DenseNet']);
        
        self.train_images_raw   = new Float32Array(NUM_TRAIN_ELEMENTS * (IMAGE_SIZE));
        self.test_images_raw    = new Float32Array(NUM_TEST_ELEMENTS * (IMAGE_SIZE));

        self.train_labels_raw   = new Uint8Array(NUM_TRAIN_ELEMENTS * (NUM_CLASSES));
        self.test_labels_raw    = new Uint8Array(NUM_TEST_ELEMENTS * (NUM_CLASSES));

        self.tf_train_images = ko.pureComputed(function () {
            return tf.tensor4d(self.train_images_raw, [self.train_images_raw.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
        }, self);

        self.tf_test_images = ko.pureComputed(function () {
            return tf.tensor4d(self.test_images_raw, [self.test_images_raw.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
        }, self);

        self.tf_train_label = ko.pureComputed(function () {
            return tf.tensor2d(self.train_labels_raw, [self.train_labels_raw.length / NUM_CLASSES, NUM_CLASSES]);
        }, self);
        self.tf_test_label = ko.pureComputed(function () {
            return tf.tensor2d(self.test_labels_raw, [self.test_labels_raw.length / NUM_CLASSES, NUM_CLASSES]);
        }, self);

        self.load_data_button_enabled = ko.pureComputed(function () {
            return  !self.data_loaded() &&
                    !self.data_is_loading() && 
                    !self.model_created() &&
                    !self.is_training();
        }, self);
        self.create_tf_model_button_enabled = ko.pureComputed(function () {
            return  self.data_loaded() && 
                    !self.model_created() &&
                    !self.is_training();
        }, self);
        self.train_button_enabled = ko.pureComputed(function () {
            return  self.data_loaded() && 
                    self.model_created() &&
                    !self.is_training();
        }, self);

        self.getTrainData = ko.pureComputed(function () {
            const xs = self.tf_train_images();
            const labels = self.tf_train_label();
            return  {xs, labels};
        }, self);

        self.getTestData = function (numExamples) {
            let xs      = self.tf_test_images();
            let labels  = self.tf_test_label();
            if (numExamples != null) {
                xs = xs.slice([0, 0, 0, 0], [numExamples, IMAGE_H, IMAGE_W, 1]);
                labels = labels.slice([0, 0], [numExamples, NUM_CLASSES]);
            }
            return  {xs, labels};
        };

        self.get_model = function() {
            let model;
            if (self.selected_model_type() === 'ConvNet') {
                model = createConvModel();
            } else if (self.selected_model_type() === 'DenseNet') {
              model = createDenseModel();
            } else {
              throw new Error(`Invalid model type: ${modelType}`);
            }
            return model;
        };

        self.create_tf_model = function () {            
            self.tf_model(self.get_model());
            self.tf_model().summary();
            self.model_created(true);
        };

        self.train_model = function () {
            self.is_training(true);

            train(  self.tf_model(), 
                    self.getTrainData(), 
                    self.getTestData(),                     
                    () => showPredictions(self.tf_model()));
        };        

        self.load_data = function (){
            self.data_is_loading(true);
            
            $.ajax({
                url: '/api.php',					  
                dataType: 'json',
                success: function(data) {
                    var row_num=0;
                    data.train.forEach(function (trainobj) {
                        var image_label = trainobj.label;
                        var pixels		= trainobj.pixels.split(",");
                        for(var i=0; i<pixels.length; i++) 
                        { 
                            pixels[i] = parseInt(pixels[i], 10); 
                        } 						                                            
                        var image_base_offset = row_num * IMAGE_SIZE;
                        var label_base_offset = row_num * NUM_CLASSES;								
                        var index = 0;
                        for (var row = 0; row < IMAGE_H; row++) {
                            for (var col = 0; col < IMAGE_W; col++, index++) {										
                                self.train_images_raw[image_base_offset + index] = pixels[index];
                            }
                        }      
                        for (var lo=0; lo<10; lo++){                                
                            self.train_labels_raw[label_base_offset+lo] = (image_label === lo) ? 1 : 0;
                        }
                        row_num++;
                    });										                    
                    row_num=0;
                    data.test.forEach(function (testobj) {
                        var image_label = testobj.label;
                        var pixels		= testobj.pixels.split(",");
                        for(var i=0; i<pixels.length; i++) 
                        { 
                            pixels[i] = parseInt(pixels[i], 10); 
                        } 						                                            
                        var image_base_offset = row_num * IMAGE_SIZE;
                        var label_base_offset = row_num * NUM_CLASSES;								
                        var index = 0;
                        for (var row = 0; row < IMAGE_H; row++) {
                            for (var col = 0; col < IMAGE_W; col++, index++) {										
                                self.test_images_raw[image_base_offset + index] = pixels[index];
                            }
                        }      
                        for (var lo=0; lo<10; lo++){                                
                            self.test_labels_raw[label_base_offset+lo] = (image_label === lo) ? 1 : 0;
                        }
                        row_num++;
                    });
                    self.data_is_loading(false);
                    self.data_loaded(true);
                },					  
            });                
        };

        self.subscribe = function (){
            self.data_is_loading.subscribe(function(newValue) {
                if (newValue) {
                    self.spin_start();
                }
                else {
                    self.spin_stop();
                }
            });
            self.is_training.subscribe(function(newValue) {
                if (newValue) {
                    self.spin_start();
                }
                else {
                    self.spin_stop();
                }
            });            
        };        

        self.get_spinner_opts = function() {
            return {
                lines: 13, // The number of lines to draw
                length: 38, // The length of each line
                width: 17, // The line thickness
                radius: 45, // The radius of the inner circle
                scale: 1, // Scales overall size of the spinner
                corners: 1, // Corner roundness (0..1)
                color: 'gray', // CSS color or array of colors
                fadeColor: 'transparent', // CSS color or array of colors
                speed: 1, // Rounds per second
                rotate: 0, // The rotation offset
                animation: 'spinner-line-fade-quick', // The CSS animation name for the lines
                direction: 1, // 1: clockwise, -1: counterclockwise
                zIndex: 2e9, // The z-index (defaults to 2000000000)
                className: 'spinner', // The CSS class to assign to the spinner
                top: '50%', // Top position relative to parent
                left: '50%', // Left position relative to parent
                shadow: '0 0 1px transparent', // Box-shadow for the lines
                position: 'absolute' // Element positioning
            };
        }

        self.spin_start = function (){
            $('#spinner').show();
        };
        self.spin_stop = function (){
            $('#spinner').hide();
        };

        var target = document.getElementById('spinner');
        var spinner = new Spinner(self.get_spinner_opts()).spin(target);       
        self.spin_stop();
        
        self.subscribe();
    };

    //var img;
    var vm = new ViewModel();
    ko.applyBindings(vm, document.getElementById('vm'));
    //vm.load_data();


const imagesElement = document.getElementById('images');

function draw2(image, canvas) {
    if (canvas) {
        //const [width, height] = [28, 28];
        canvas.width = 28;
        canvas.height = 28;
        const ctx = canvas.getContext('2d');
        const imageData = new ImageData(width, height);
        const data = image.dataSync();
        for (let i = 0; i < height * width; ++i) {
            const j = i * 4;
            imageData.data[j + 0] = data[i] * 255;
            imageData.data[j + 1] = data[i] * 255;
            imageData.data[j + 2] = data[i] * 255;
            imageData.data[j + 3] = 255;
        }
        ctx.putImageData(imageData, 0, 0);
    }
}

function showTestResults(batch, predictions, labels) {
  const testExamples = batch.xs.shape[0];
  imagesElement.innerHTML = '';
  for (let i = 0; i < testExamples; i++) {
    const image = batch.xs.slice([i, 0], [1, batch.xs.shape[1]]);

    const div = document.createElement('div');
    div.className = 'pred-container';

    const canvas = document.createElement('canvas');
    canvas.className = 'prediction-canvas';
    draw2(image.flatten(), canvas);

    const pred = document.createElement('div');

    const prediction = predictions[i];
    const label = labels[i];
    const correct = prediction === label;

    pred.className = `pred ${(correct ? 'pred-correct' : 'pred-incorrect')}`;
    pred.innerText = `pred: ${prediction}`;

    div.appendChild(pred);
    div.appendChild(canvas);

    imagesElement.appendChild(div);
  }
}

/**
 * Show predictions on a number of test examples.
 *
 * @param {tf.Model} model The model to be used for making the predictions.
 */
async function showPredictions(model) {
    
  const testExamples = 10;  
  const examples = vm.getTestData(testExamples);

  // Code wrapped in a tf.tidy() function callback will have their tensors freed
  // from GPU memory after execution without having to call dispose().
  // The tf.tidy callback runs synchronously.
  tf.tidy(() => {
    const output = model.predict(examples.xs);

    // tf.argMax() returns the indices of the maximum values in the tensor along
    // a specific axis. Categorical classification tasks like this one often
    // represent classes as one-hot vectors. One-hot vectors are 1D vectors with
    // one element for each output class. All values in the vector are 0
    // except for one, which has a value of 1 (e.g. [0, 0, 0, 1, 0]). The
    // output from model.predict() will be a probability distribution, so we use
    // argMax to get the index of the vector element that has the highest
    // probability. This is our prediction.
    // (e.g. argmax([0.07, 0.1, 0.03, 0.75, 0.05]) == 3)
    // dataSync() synchronously downloads the tf.tensor values from the GPU so
    // that we can use them in our normal CPU JavaScript code
    // (for a non-blocking version of this function, use data()).
    const axis = 1;
    const labels = Array.from(examples.labels.argMax(axis).dataSync());
    const predictions = Array.from(output.argMax(axis).dataSync());
    //console.info(examples, predictions, labels);
    //ui.showTestResults(examples, predictions, labels);

      showTestResults(examples, predictions, labels);
  });
}
//TODO:   processing.js functions

    function setup() {
        var canvas = createCanvas(28, 28);
        canvas.parent("p5container");
    }
    //function draw() {        
    //    try
    //    {
    //        //image(vm.train_data()[0], 0, 0);
    //        if (vm.loading()) {
    //            return;
    //        }
    //        if (vm.fetching()) {
    //            return;
    //        }
    //        //console.info('drawging ');
    //        vm.fetching(true);
    //        //console.info('fetch issued ');
    //        vm.db().transaction('r', vm.db().images, () => {
    //            var rand_index = Math.floor((Math.random() * 1000) + 1);
    //            vm.db().images.get(rand_index).then (function (firstImage) {
    //                //console.info('get  ' + rand_index);
    //                if ('undefined' !== typeof firstImage) {
    //                    var tmppixels = createImage(28, 28);
    //                    tmppixels.loadPixels();
    //                    for (var i = 0; i< firstImage.pixels.length; i++) {
    //                        tmppixels.pixels[i] = firstImage.pixels[i];
    //                    }
    //                    tmppixels.updatePixels();
    //                    background(0);
    //                    image(tmppixels, 0, 0);
    //                }
    //                vm.fetching(false);
    //            });
    //        }).catch(function (e) {
    //            vm.fetching(false);
    //            console.error (e.stack || e);
    //        });
    //    }
    //    catch (error)
    //    {
    //        console.error(error);
    //    }
    //}
    // function write_rgba_pixel(image, row, col, red, green, blue, alpha) {
    //     var index = (row + col * image.width) * 4;
    //     image.pixels[index] = red;
    //     image.pixels[index + 1] = green;
    //     image.pixels[index + 2] = blue;
    //     image.pixels[index + 3] = alpha;
    // }
    // function create_processingjs_image(train_csv_row) {
    //     var mnistpixels = createImage(28, 28);
    //     mnistpixels.loadPixels();
    //     var image_label = train_csv_row.data[0][0];
    //     var index = 1;
    //     for (var row = 0; row < mnistpixels.height; row++) {
    //         for (var col = 0; col < mnistpixels.width; col++, index++) {
    //             var gray_val = train_csv_row.data[0][index];
    //             write_rgba_pixel(mnistpixels, row, col, gray_val, gray_val, gray_val, 255);
    //         }
    //     }
    //     mnistpixels.updatePixels();
    //     return mnistpixels;
    // }

$(document).ready(function() {    
});

    </script>
</body>
</html>