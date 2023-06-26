<!DOCTYPE html>
<head>
    <title>Trabaio com csv 2,99</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="icon" type="image/x-icon" href="./icon.ico">
</head>

<body>
<div class="mt-3 container ">
    <form enctype='multipart/form-data'>

        <label class="mt-3 fw-bold">Arquivos:</label>
        <div class="mt-2 input-group input-group-sm">
            <input type="file" id="files" name="files[]" multiple class="form-control form-control-sm">

            <span class="input-group-text ms-1">Usar pasta</span>
            <input name="target_folder" id="sourceFolder" type="text" class="form-control"/>
        </div>


        <label class="mt-3 fw-bold">Configuração:</label>
        <div class="mt-2 input-group input-group-sm">
            <span class="input-group-text">Separador</span>
            <select name="separator" id="separator" type="text" class="form-select form-select-sm">
                <option value="comma" selected>Vírgula</option>
                <option value="tab">Tab</option>
                <option value="pipe">Pipe [ | ]</option>
                <option value="hyphen">Hífen [ - ]</option>
            </select>

            <span class="input-group-text ms-1">Schema</span>
            <input name="target_schema" id="targetSchema" type="text" class="form-control"/>

            <span class="input-group-text ms-1">Ação</span>
            <select name="action" id="action" type="text" class="form-select form-select-sm">
                <option value="create_table">Criar tabela</option>
            </select>
        </div>

        <div class="mt-2  input-group input-group-sm">
            <span class="input-group-text">Encoding de</span>
            <select name="source_encoding" id="sourceEncoding" type="text" class="form-select form-select-sm">
                <option value="" selected>Automático</option>
                <option value="UTF-8">UTF-8</option>
                <option value="ISO-8859-1">ISO-8859-1 (Latin1)</option>
            </select>

            <span class="input-group-text ms-1">para</span>
            <select name="target_encoding" id="targetEncoding" type="text" class="form-select form-select-sm">
                <option value="ISO-8859-1">ISO-8859-1 (Latin1)</option>
                <option value="UTF-8">UTF-8</option>
            </select>
        </div>
        <button class="btn btn-outline-primary btn-light mt-2" type="button" id="generateBtn">Gerar</button>
    </form>
</div>

<script>
    window.onload = function () {
        const generateButton = document.getElementById('generateBtn');
        generateButton.addEventListener('click', sendRequest);

        function getValueById(id) {
            const element = document.getElementById(id);

            if (element) {
                return element.value;
            }
            return '';
        }

        function checkRequiredFields() {
            return getValueById('files').trim() !== '' || getValueById('sourceFolder').trim() !== '';
        }

        function buildFormData() {
            const formData = new FormData;
            const filesGroup = document.getElementById('files');
            const sourceFolder = getValueById('sourceFolder');

            formData.append('separator', getValueById('separator'));
            formData.append('action', getValueById('action'));
            formData.append('sourceEncoding', getValueById('sourceEncoding'));
            formData.append('targetEncoding', getValueById('targetEncoding'));
            formData.append('targetSchema', getValueById('targetSchema'));


            if (sourceFolder) {
                formData.append('sourceFolder', sourceFolder);
            } else {
                for (let i = 0; i < filesGroup.files.length; i++) {
                    formData.append(`file_${i}`, filesGroup.files[i]);
                }
            }

            return formData;
        }

        function sendRequest() {
            if (!checkRequiredFields()) {
                return alert('Campos obrigatórios não preenchidos.');
            }

            const xhr = new XMLHttpRequest;
            xhr.open('POST', 'scripts/manager.php', true);
            xhr.send(buildFormData());

            xhr.onreadystatechange = function (response) {
                if (this.readyState === 4) {
                    if (this.status === 200) {
                        return alert('Ação realizada!');
                    } else {
                        alert('Houve um erro ao gerar o arquivo.')
                    }
                }
            }

        }
    }
</script>
</body>