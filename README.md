# tf_js_mnist

Mnist sample using tensorflow.js.
Example application styled after the tensorflow.js mnist example.

# Creating the sqlite3 database from  kaggle csv files

- Extract kaggle_dataset\kaggle_dataset.zip
- Run kaggle_dataset\create_db.bat
- Copy mnist.db to nginx-1.14.2\html

Github has a 50MB file limit.  Mnist.db and source csv files are too large.

# Starting The Webiste

- Run go.bat
- http://127.0.0.1:8080

## Stack

### Server

- Nginx w/ PHP as FastCGI
- Sqlite3

Why: The simpliest web setup possible standalone setup on windows that can be checked in github.  Focus is on learning TF.js workflow.

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