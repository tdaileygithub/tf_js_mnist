<!DOCTYPE html>

<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
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
    <script src="/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="/css/bootstrap.min.css" />
    <title>MNIST using Tensorflow.js</title>
	<style>
    .pred {
      font-size: 20px;
      line-height: 25px;
      width: 100px;
    }
    .pred-correct {
      background-color: #00cf00;
    }
    .pred-incorrect {
      background-color: red;
    }
	</style>
</head>
<body>
	<?php 
	echo '<p>Hello World</p>'; 
	?> 
    <div id="vm">
        <div class="container">
            <div class="row">
                <div class="col-4">
                </div>
                <div class="col-4">
                    <ul data-bind="foreach: messages">
                        <li>
                            <span data-bind="text: $data"> </span>:
                        </li>
                    </ul>
                </div>
                <div class="col-4">
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
                <div class="col-12">
                    <div id="images"></div>
                </div>
            </div>
        </div>

    </div>

    <script>


let data;
const IMAGE_H = 28;
const IMAGE_W = 28;
const IMAGE_SIZE = IMAGE_H * IMAGE_W;
const NUM_CLASSES = 10;

//GOOGLE_TF_EXAMPLE
//const NUM_DATASET_ELEMENTS = 65000;
//const NUM_TRAIN_ELEMENTS = 55000;

//const NUM_DATASET_ELEMENTS = 1201;
//const NUM_TRAIN_ELEMENTS = 1000;

const NUM_DATASET_ELEMENTS = 33600 + 8400;
const NUM_TRAIN_ELEMENTS = 33600 ;

const NUM_TEST_ELEMENTS = NUM_DATASET_ELEMENTS - NUM_TRAIN_ELEMENTS;
const MNIST_IMAGES_SPRITE_PATH =
    'https://storage.googleapis.com/learnjs-data/model-builder/mnist_images.png';
const MNIST_LABELS_PATH =
    'https://storage.googleapis.com/learnjs-data/model-builder/mnist_labels_uint8';


        var ViewModel = function () {
            var self = this;
            self.db_local           = "digits-db";
            self.load_data          =  ko.observable(true);
            self.test_loading       =  ko.observable(true);
            self.training_loading   =  ko.observable(true);
            self.loading = ko.pureComputed(function () {
                return self.test_loading() || self.training_loading();
            }, self);

            self.messages           =  ko.observableArray([]);
            self.fetching           =  ko.observable(false);
            
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

            self.getTrainData = ko.pureComputed(function () {
                const xs = self.tf_train_images();
                const labels = self.tf_train_label();
                return  {xs, labels};
            }, self);

  
            self.load_tf = function () {

                async function load() {
                    //GOOGLE_TF_EXAMPLE
                    //data = new MnistData();
                    //await data.load();

                    self.messages.push('Creating Tensorflow Model');

                    const model = createModel();
                    model.summary();

					self.messages.push('Training ' + NUM_TRAIN_ELEMENTS + ' ' + NUM_TEST_ELEMENTS);
                    train(model, () => showPredictions(model));
                }
                load();
            }

            
            self.getTestData = function (numExamples) {
                return ko.pureComputed(function() {
                let xs = self.tf_test_images();
                let labels = self.tf_test_label();
                if (numExamples != null) {
                  xs = xs.slice([0, 0, 0, 0], [numExamples, IMAGE_H, IMAGE_W, 1]);
                  labels = labels.slice([0, 0], [numExamples, NUM_CLASSES]);
                }

                return  {xs, labels};
            }, self);
            };

            self.loading.subscribe(function (newVal) {
                if (!newVal){
                    self.load_tf();
                }                                                
            });

            self.load_data = function (){

				self.messages.push('fetching train and test data');
				$.ajax({
					url: '/api.php',					  
					dataType: 'json',
					success: function(data) {
						var row_num=0;
						self.messages.push('processing training data');
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
						self.messages.push('processing test data');
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
						self.training_loading(false);
						self.test_loading(false);
					},					  
				});
                 
            };

        };

            //var img;
            var vm = new ViewModel();
            ko.applyBindings(vm, document.getElementById('vm'));
            vm.load_data();




class MnistData {
  constructor() {}

  async load() {
    // Make a request for the MNIST sprited image.
    const img = new Image();
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const imgRequest = new Promise((resolve, reject) => {
      img.crossOrigin = '';
      img.onload = () => {
        img.width = img.naturalWidth;
        img.height = img.naturalHeight;

        const datasetBytesBuffer =
            new ArrayBuffer(NUM_DATASET_ELEMENTS * IMAGE_SIZE * 4);

        const chunkSize = 5000;
        canvas.width = img.width;
        canvas.height = chunkSize;

        for (let i = 0; i < NUM_DATASET_ELEMENTS / chunkSize; i++) {
          const datasetBytesView = new Float32Array(
              datasetBytesBuffer, i * IMAGE_SIZE * chunkSize * 4,
              IMAGE_SIZE * chunkSize);
          ctx.drawImage(
              img, 0, i * chunkSize, img.width, chunkSize, 0, 0, img.width,
              chunkSize);

          const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

          for (let j = 0; j < imageData.data.length / 4; j++) {
            // All channels hold an equal value since the image is grayscale, so
            // just read the red channel.
            datasetBytesView[j] = imageData.data[j * 4] / 255;
          }
        }
        this.datasetImages = new Float32Array(datasetBytesBuffer);

        resolve();
      };
      img.src = MNIST_IMAGES_SPRITE_PATH;
    });

    const labelsRequest = fetch(MNIST_LABELS_PATH);
    const [imgResponse, labelsResponse] =
        await Promise.all([imgRequest, labelsRequest]);

    this.datasetLabels = new Uint8Array(await labelsResponse.arrayBuffer());

    // Slice the the images and labels into train and test sets.
    this.trainImages =
        this.datasetImages.slice(0, IMAGE_SIZE * NUM_TRAIN_ELEMENTS);
    this.testImages = this.datasetImages.slice(IMAGE_SIZE * NUM_TRAIN_ELEMENTS);
    this.trainLabels =
        this.datasetLabels.slice(0, NUM_CLASSES * NUM_TRAIN_ELEMENTS);
    this.testLabels =
        this.datasetLabels.slice(NUM_CLASSES * NUM_TRAIN_ELEMENTS);
  }

  /**
   * Get all training data as a data tensor and a labels tensor.
   *
   * @returns
   *   xs: The data tensor, of shape `[numTrainExamples, 28, 28, 1]`.
   *   labels: The one-hot encoded labels tensor, of shape
   *     `[numTrainExamples, 10]`.
   */
  getTrainData() {
    const xs = tf.tensor4d(
        this.trainImages,
        [this.trainImages.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
    const labels = tf.tensor2d(
        this.trainLabels, [this.trainLabels.length / NUM_CLASSES, NUM_CLASSES]);
    return {xs, labels};
  }

  /**
   * Get all test data as a data tensor a a labels tensor.
   *
   * @param {number} numExamples Optional number of examples to get. If not
   *     provided,
   *   all test examples will be returned.
   * @returns
   *   xs: The data tensor, of shape `[numTestExamples, 28, 28, 1]`.
   *   labels: The one-hot encoded labels tensor, of shape
   *     `[numTestExamples, 10]`.
   */
  getTestData(numExamples) {
    let xs = tf.tensor4d(
        this.testImages,
        [this.testImages.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
    let labels = tf.tensor2d(
        this.testLabels, [this.testLabels.length / NUM_CLASSES, NUM_CLASSES]);

    if (numExamples != null) {
      xs = xs.slice([0, 0, 0, 0], [numExamples, IMAGE_H, IMAGE_W, 1]);
      labels = labels.slice([0, 0], [numExamples, NUM_CLASSES]);
    }
    return {xs, labels};
  }
}

/**
 * Creates a convolutional neural network (Convnet) for the MNIST data.
 *
 * @returns {tf.Model} An instance of tf.Model.
 */
function createConvModel() {
  // Create a sequential neural network model. tf.sequential provides an API
  // for creating "stacked" models where the output from one layer is used as
  // the input to the next layer.
  const model = tf.sequential();

  // The first layer of the convolutional neural network plays a dual role:
  // it is both the input layer of the neural network and a layer that performs
  // the first convolution operation on the input. It receives the 28x28 pixels
  // black and white images. This input layer uses 16 filters with a kernel size
  // of 5 pixels each. It uses a simple RELU activation function which pretty
  // much just looks like this: __/
  model.add(tf.layers.conv2d({
    inputShape: [IMAGE_H, IMAGE_W, 1],
    kernelSize: 3,
    filters: 16,
    activation: 'relu'
  }));

  // After the first layer we include a MaxPooling layer. This acts as a sort of
  // downsampling using max values in a region instead of averaging.
  // https://www.quora.com/What-is-max-pooling-in-convolutional-neural-networks
  model.add(tf.layers.maxPooling2d({poolSize: 2, strides: 2}));

  // Our third layer is another convolution, this time with 32 filters.
  model.add(tf.layers.conv2d({kernelSize: 3, filters: 32, activation: 'relu'}));

  // Max pooling again.
  model.add(tf.layers.maxPooling2d({poolSize: 2, strides: 2}));

  // Add another conv2d layer.
  model.add(tf.layers.conv2d({kernelSize: 3, filters: 32, activation: 'relu'}));

  // Now we flatten the output from the 2D filters into a 1D vector to prepare
  // it for input into our last layer. This is common practice when feeding
  // higher dimensional data to a final classification output layer.
  model.add(tf.layers.flatten({}));

  model.add(tf.layers.dense({units: 64, activation: 'relu'}));

  // Our last layer is a dense layer which has 10 output units, one for each
  // output class (i.e. 0, 1, 2, 3, 4, 5, 6, 7, 8, 9). Here the classes actually
  // represent numbers, but it's the same idea if you had classes that
  // represented other entities like dogs and cats (two output classes: 0, 1).
  // We use the softmax function as the activation for the output layer as it
  // creates a probability distribution over our 10 classes so their output
  // values sum to 1.
  model.add(tf.layers.dense({units: 10, activation: 'softmax'}));

  return model;
}

/**
 * Creates a model consisting of only flatten, dense and dropout layers.
 *
 * The model create here has approximately the same number of parameters
 * (~31k) as the convnet created by `createConvModel()`, but is
 * expected to show a significantly worse accuracy after training, due to the
 * fact that it doesn't utilize the spatial information as the convnet does.
 *
 * This is for comparison with the convolutional network above.
 *
 * @returns {tf.Model} An instance of tf.Model.
 */
function createDenseModel() {
  const model = tf.sequential();
  model.add(tf.layers.flatten({inputShape: [IMAGE_H, IMAGE_W, 1]}));
  model.add(tf.layers.dense({units: 42, activation: 'relu'}));
  model.add(tf.layers.dense({units: 10, activation: 'softmax'}));
  return model;
}

/**
 * Compile and train the given model.
 *
 * @param {*} model The model to
 */
async function train(model, onIteration) {
  //ui.logStatus('Training model...');

  // Now that we've defined our model, we will define our optimizer. The
  // optimizer will be used to optimize our model's weight values during
  // training so that we can decrease our training loss and increase our
  // classification accuracy.

  // The learning rate defines the magnitude by which we update our weights each
  // training step. The higher the value, the faster our loss values converge,
  // but also the more likely we are to overshoot optimal parameters
  // when making an update. A learning rate that is too low will take too long
  // to find optimal (or good enough) weight parameters while a learning rate
  // that is too high may overshoot optimal parameters. Learning rate is one of
  // the most important hyperparameters to set correctly. Finding the right
  // value takes practice and is often best found empirically by trying many
  // values.
  const LEARNING_RATE = 0.01;

  // We are using rmsprop as our optimizer.
  // An optimizer is an iterative method for minimizing an loss function.
  // It tries to find the minimum of our loss function with respect to the
  // model's weight parameters.
  const optimizer = 'rmsprop';

  // We compile our model by specifying an optimizer, a loss function, and a
  // list of metrics that we will use for model evaluation. Here we're using a
  // categorical crossentropy loss, the standard choice for a multi-class
  // classification problem like MNIST digits.
  // The categorical crossentropy loss is differentiable and hence makes
  // model training possible. But it is not amenable to easy interpretation
  // by a human. This is why we include a "metric", namely accuracy, which is
  // simply a measure of how many of the examples are classified correctly.
  // This metric is not differentiable and hence cannot be used as the loss
  // function of the model.
  model.compile({
    optimizer,
    loss: 'categoricalCrossentropy',
    metrics: ['accuracy'],
  });

  // Batch size is another important hyperparameter. It defines the number of
  // examples we group together, or batch, between updates to the model's
  // weights during training. A value that is too low will update weights using
  // too few examples and will not generalize well. Larger batch sizes require
  // more memory resources and aren't guaranteed to perform better.
  const batchSize = 320;

  // Leave out the last 15% of the training data for validation, to monitor
  // overfitting during training.
  const validationSplit = 0.15;

  // Get number of training epochs from the UI.
  //const trainEpochs = ui.getTrainEpochs();
  const trainEpochs = 5;

  // We'll keep a buffer of loss and accuracy values over time.
  let trainBatchCount = 0;

//GOOGLE_TF_EXAMPLE
//const trainData = data.getTrainData();
//const testData = data.getTestData();
const trainData = vm.getTrainData();
const testData = vm.getTestData()();



  const totalNumBatches =
      Math.ceil(trainData.xs.shape[0] * (1 - validationSplit) / batchSize) *
      trainEpochs;

  // During the long-running fit() call for model training, we include
  // callbacks, so that we can plot the loss and accuracy values in the page
  // as the training progresses.
  let valAcc;
  await model.fit(trainData.xs, trainData.labels, {
    batchSize,
    validationSplit,
    epochs: trainEpochs,
    callbacks: {
      onBatchEnd: async (batch, logs) => {
        trainBatchCount++;

        if (batch % 100 === 0) {
            console.info(
                `Training... (` +
                `${(trainBatchCount / totalNumBatches * 100).toFixed(1)}%` +
                ` complete). To stop training, refresh or close page.`);
            console.info('batch,loss,accuracry',trainBatchCount, logs.loss,logs.acc);          
        }


        //ui.logStatus(
        //    `Training... (` +
        //    `${(trainBatchCount / totalNumBatches * 100).toFixed(1)}%` +
        //    ` complete). To stop training, refresh or close page.`);
        //ui.plotLoss(trainBatchCount, logs.loss, 'train');
        //ui.plotAccuracy(trainBatchCount, logs.acc, 'train');
        if (onIteration && batch % 100 === 0) {
          onIteration('onBatchEnd', batch, logs);
        }
        await tf.nextFrame();
      },
      onEpochEnd: async (epoch, logs) => {
        valAcc = logs.val_acc;
        //ui.plotLoss(trainBatchCount, logs.val_loss, 'validation');
        //ui.plotAccuracy(trainBatchCount, logs.val_acc, 'validation');
        if (onIteration) {
          onIteration('onEpochEnd', epoch, logs);
        }
        await tf.nextFrame();
      }
    }
  });

  const testResult = model.evaluate(testData.xs, testData.labels);
  const testAccPercent = testResult[1].dataSync()[0] * 100;
  const finalValAccPercent = valAcc * 100;
  console.info(`Final validation accuracy: ${finalValAccPercent.toFixed(1)}%; ` + `Final test accuracy: ${testAccPercent.toFixed(1)}%`);
  //ui.logStatus(
  //    `Final validation accuracy: ${finalValAccPercent.toFixed(1)}%; ` +
  //    `Final test accuracy: ${testAccPercent.toFixed(1)}%`);
}

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
    
  const testExamples = 50;
  //GOOGLE_TF_EXAMPLE
  //const examples = data.getTestData(testExamples);
  const examples = vm.getTestData(testExamples)();

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

function createModel() {
  let model;
  //const modelType = ui.getModelTypeId();
  //if (modelType === 'ConvNet') {
    model = createConvModel();
  //} else if (modelType === 'DenseNet') {
  //  model = createDenseModel();
  //} else {
  //  throw new Error(`Invalid model type: ${modelType}`);
  //}
  return model;
}
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
            function write_rgba_pixel(image, row, col, red, green, blue, alpha) {
                var index = (row + col * image.width) * 4;
                image.pixels[index] = red;
                image.pixels[index + 1] = green;
                image.pixels[index + 2] = blue;
                image.pixels[index + 3] = alpha;
            }
            function create_processingjs_image(train_csv_row) {
                var mnistpixels = createImage(28, 28);
                mnistpixels.loadPixels();
                var image_label = train_csv_row.data[0][0];
                var index = 1;
                for (var row = 0; row < mnistpixels.height; row++) {
                    for (var col = 0; col < mnistpixels.width; col++, index++) {
                        var gray_val = train_csv_row.data[0][index];
                        write_rgba_pixel(mnistpixels, row, col, gray_val, gray_val, gray_val, 255);
                    }
                }
                mnistpixels.updatePixels();
                return mnistpixels;
            }

            $(document).ready(function () {
                
            });

    </script>
</body>
</html>