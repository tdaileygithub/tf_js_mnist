# tf_js_mnist

![Alt text](images/001.jpg?raw=true "Tf.js - Kaggle Digit Recognizer")

Mnist sample using tensorflow.js.
Example application styled after the tensorflow.js mnist example.

*Scored: 0.96971 using tf.js default mnist model*

# Creating the sqlite3 database from  kaggle csv files

- Extract kaggle_dataset\kaggle_dataset.zip
- Run kaggle_dataset\create_db.bat
- Copy mnist.db to nginx-1.14.2\html

Github has a 50MB file limit.  Mnist.db and source csv files are too large.

# Starting The Webiste

- Run go.bat
- http://127.0.0.1:8080

## Stack

- Nginx w/ PHP as FastCGI
- Sqlite3
- IndexedDB

*Why*: The simpliest web setup on windows that can be checked in github.  Focus is on learning TF.js workflow.

### Web Frontend

- Tensorflow.js
- Knockout
- Bootstrap 
- JQuery
- Dexie.js
- spin.js
- d3.js + c3.js
- PNotify
- Processing.js
- Linq.js