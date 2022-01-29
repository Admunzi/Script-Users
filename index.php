<?php
session_start();
if (!isset($_SESSION['auth'])) {
    $_SESSION['auth'] = false;
}
if (!isset($_SESSION['nombreFichero'])) {
    $_SESSION['nombreFichero'] = "";
}

define("MAXSIZE", 2000000);
define("DIRUPLOAD", "files/");

$curso = $grupo =  $fecha = $opcion = "";
$patron = "AAaan_gc";

$errorFile = $errorOption = $errorCurso = $errorGrupo = "";

$lprocesaFormulario = FALSE;
$lerror = FALSE;


function clearData($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
};

if (isset($_POST['mandar'])) {
    if (!empty($_POST['fecha'])) {
        $fecha = clearData($_POST['fecha']);
    }

    if (!empty($_POST['curso'])) {
        $curso = clearData($_POST['curso']);
    }else{
        $errorCurso = "Necessary";
        $lerror = true;
        $curso = clearData($_POST['curso']);
    }

    if (!empty($_POST['grupo'])) {
        $grupo = clearData($_POST['grupo']);
    }else{
        $errorGrupo = "Necessary";
        $lerror = true;
        $grupo = clearData($_POST['grupo']);
    }

    if (isset($_POST['patron'])) {
        $patron = clearData($_POST['patron']);
        //Comprobamos si tiene alguno patron sino, le ponemos uno por defecto
        if (!(str_contains($patron, 'A') || str_contains($patron, 'a') || str_contains($patron, 'n')|| str_contains($patron, 'c')|| str_contains($patron, 'g'))) {
            $patron = "AAaan_gc";
        }
    }

    if (empty($_FILES['file']['size'])) {
        $errorFile = "Necessary";
        $lerror = true;
    }else{
        moverArchivo();
    }

    if (empty($_POST['opcion'])) {
        $errorOption = "Necessarytorio";
        $lerror = true;
    }else{
        $opcion = clearData($_POST['opcion']);
    }

    //Empezamos el codigo para hacer los patrones
    if (!$lerror) {
        $aAlumnos = leerFichero();
        // var_dump($aAlumnos);
        $aFormateados = generarAlumnosFormateados($aAlumnos,$curso,$grupo,$patron);
        switch ($opcion) {
            case 'mysql':
                generarMysql($aFormateados,$grupo,$curso,$fecha);
                $_SESSION['nombreFichero'] = "myqsl.sql";
                break;
            case 'linux':
                generarLinux($aFormateados,$grupo,$curso,$fecha);
                $_SESSION['nombreFichero'] = "linux.sh";
                break;
            case 'oracle':
                # code...
                break;
            default:
                break;
        }
        $_SESSION['auth'] = true;
        devolvemosElArchivo();

    }
}

function moverArchivo(){
    $allowedExts = array("txt");
    $allowedFormat = array("text/plain");

    // pathinfo();
    $aNombre = explode(".", $_FILES["file"]["name"]);
    $ext = end($aNombre);

    if (($_FILES["file"]["size"] < MAXSIZE) && (in_array($ext, $allowedExts)) && (in_array($_FILES["file"]["type"], $allowedFormat))) {
        if ($_FILES["file"]["error"] > 0)    {
            echo "Return Code: " . $_FILES["file"]["error"] . "<br/>";
    } else {
            $filename = $_FILES["file"]["name"];

            move_uploaded_file($_FILES["file"]["tmp_name"], DIRUPLOAD . $filename);
        }

    }
}

function devolvemosElArchivo(){
    header('Location: backFile.php');
}

function generarLinux($aFormateados,$grupo,$curso,$fecha){
    $estructura = $grupo.$curso.$fecha;

    $fh = fopen("files/output.txt", "w");
    fputs($fh,"#Creamos la carpeta \n\n");
    fputs($fh,"groupadd ".$estructura."\n");
    fputs($fh,"mkdir /home/".$estructura."\n");
    fputs($fh,"cd /home \n");
    fputs($fh, "chgrp ".$estructura." ".$estructura."\n");
    fputs($fh,"cd /home/".$estructura."\n\n");

    fputs($fh,"#Creamos los usuarios \n\n");

    foreach ($aFormateados as $key => $value) {
        fputs($fh, "mkdir ".$value."/public_html/ -p\n");
        fputs($fh, "useradd ".$value." -M -d /home/".$estructura."/".$value." -s /bin/bash -g ".$estructura."\n");
        fputs($fh, "echo \"".$value.":1234\" | sudo chpasswd \n");
        fputs($fh, "chown ".$value." ".$value."\n\n");
        //Quito el añadir grupo         fputs($fh, "chown ".$value.":".$estructura." ".$value."\n\n");
    }
    fclose ($fh);
}

function generarMysql($aFormateados,$grupo,$curso,$fecha){
    $estructura = $grupo.$curso.$fecha;
    
    $fh = fopen("files/output.txt", "w");
    fputs($fh,"CREATE DATABASE ". $estructura ."; \n\n");
    
    foreach ($aFormateados as $key => $value) {
        fputs($fh,"CREATE USER '".$value."'@'localhost' IDENTIFIED BY '".$value."';\n");
        fputs($fh,"GRANT ALL PRIVILEGES ON ". $estructura .".* TO '".$value."'@'localhost';\n");
    }
    fclose ($fh);
}

function leerFichero(){
    $file = fopen("files/RelAluProMatUni.txt","r") or exit("No se puede abrir el fichero");

    $i = 0;
    $cantidadLineaLarga = 2;
    while (!feof($file)) {
        //Quitamos espacios al final
        $linea = rtrim(fgets($file));

        if ($cantidadLineaLarga == 0) {
            //Separamos cada alumno, primero separamos el nombre por todo lo que esté detrás de la coma
            if (!empty($linea)) {
                $partesPrincipales = explode(", ",$linea);
                $partesNombreYApellido = explode(" ",$partesPrincipales[0]);
    
                //Guardamos el nombre, apellido1 y apellido2
                $aAlumnos[$i]['nombre'] = strtolower(eliminarAcentos($partesPrincipales[1]));
                $aAlumnos[$i]['apellido1'] = strtolower(eliminarAcentos($partesNombreYApellido[0]));
                $aAlumnos[$i++]['apellido2'] = strtolower(eliminarAcentos($partesNombreYApellido[1]));    
            }
        }

        if ($linea == "----------------------------------") {
            $cantidadLineaLarga--;
        }
    }
    fclose($file);
    return $aAlumnos;
}

function eliminarAcentos($cadena){
		
    //Reemplazamos la A y a
    $cadena = str_replace(
    array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª'),
    array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a'),
    $cadena
    );

    //Reemplazamos la E y e
    $cadena = str_replace(
    array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
    array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'),
    $cadena );

    //Reemplazamos la I y i
    $cadena = str_replace(
    array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
    array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'),
    $cadena );

    //Reemplazamos la O y o
    $cadena = str_replace(
    array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
    array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'),
    $cadena );

    //Reemplazamos la U y u
    $cadena = str_replace(
    array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
    array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'),
    $cadena );

    //Reemplazamos la N, n, C y c
    $cadena = str_replace(
    array('Ñ', 'ñ', 'Ç', 'ç'),
    array('N', 'n', 'C', 'c'),
    $cadena
    );
    
    return $cadena;
}

function generarAlumnosFormateados($aAlumnos,$curso,$grupo,$patron){
    $aLista = array();
    foreach ($aAlumnos as $key => $alumno) {
        $contPrimApell = $contSegApell = $contNombre = 0;
        $usuarioFinal = "";
        for ($i=0; $i < strlen($patron); $i++) { 
            switch ($patron[$i]) {
                case 'A':
                    $usuarioFinal .= $alumno['apellido1'][$contPrimApell++];
                    break;
                case 'a':
                    $usuarioFinal .= $alumno['apellido2'][$contSegApell++];
                    break;
                case 'n':
                    $usuarioFinal .= $alumno['nombre'][$contNombre++];
                    break;
                case 'c':
                    $usuarioFinal .= $curso;
                    break;
                case 'g':
                    $usuarioFinal .= $grupo;
                    break;
                default:
                    $usuarioFinal .= $patron[$i];
                    break;
            }
        }
        $usuarioFinal .= "_1";
        //Comprobamos que no hay repetidos
        while (in_array($usuarioFinal,$aLista)) {
            $indice = array_search($usuarioFinal, $aLista);
            //comprobamos cual es el ultimo
            preg_match('/[0-9]+$/',$aLista[$indice], $coincidencia);

            $usuarioFinal = preg_replace('/[0-9]+/',$coincidencia[0]+1,$usuarioFinal);
        }

        array_push($aLista,$usuarioFinal);
    }
    return $aLista;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link rel="stylesheet" href="css/style.css">
</head>
    <body>
        <div class="principal">
            <h1>User creation</h1>
            <p><a href="#popup">Info</a></p>

            <div id="popup" class="overlay">
                <div id="popupBody">
                    <h2>Pattern Info</h2>
                    <a id="cerrar" href="#">&times;</a>
                    <div>
                        <p>
                            <b>From left to right</b> <br><br>
                            <b>A</b> (first letter last name) <br>
                            <b>a</b> (second letter last name) <br>
                            <b>n</b> (letter name) <br>
                            <b>c</b> (school year) <br>
                            <b>g</b> (group) <br><br>
                            E.g: aanAA_cg <br>
                            E.g: gc_aAnnA <br>
                            E.g: AA_cg_nnn <br>
                        </p>
                    </div>
                </div>
            </div>
            
            <form action="" method="post" enctype="multipart/form-data">
                <p>School year:</p>
                <input type="text" name="curso" placeholder="E.g: DAW, 1, 4" value="<?php echo $curso?>">
                <span><?php echo ("*".$errorCurso)?></span><br/>

                <p>Group:</p>
                <input type="text" name="grupo" placeholder="E.g: 2, 2B" value="<?php echo $grupo?>">
                <span><?php echo ("*".$errorGrupo)?></span><br/>

                <p>Pattern</p>
                <input type="text" name="patron" placeholder="E.g: AAaann_cg" value="<?php echo $patron?>">

                <p>Date</p>
                <select name="fecha">
                    <?php
                        $fecha = substr(getdate()['year'],2)-1;
                        for ($i=0; $i < 10; $i++) { 
                            echo ("<option value=\"".$fecha."".++$fecha."\">".--$fecha."/".++$fecha."</option>");
                        }
                    ?>
                </select>
                
                <p>Options:</p> 
                <input type="radio" name="opcion" value="mysql">Mysql
                <input type="radio" name="opcion" value="linux">Linux 
                <span><?php echo ("*".$errorOption)?></span>

                <p>File</p>
                <input type="file" name="file" id="file">
                <span><?php echo ("*".$errorFile)?></span><br/>

                <p><input type="submit" name="mandar" value="Send"></p>
            </form>
        </div>
    </body>
</html>