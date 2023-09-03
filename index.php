<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Document</title>
</head>

<body>
   <form method="post" action="script.php">
      <textarea placeholder="Введите список запросов" style="width:400px;height:500px;white-space:pre-line;" , name="phrases" required></textarea><br>
      <input type="radio" id="russia" name="region" value=225 checked><label for="russia">Россия</label>
      <input type="radio" id="moscow" name="region" value=213><label for="moscow">Москва</label>
      <input type="radio" id="dubna" name="region" value=215><label for="dubna">Дубна</label> <br>
      <input type="text" name="token" id="token" required><label for="token">Введите токен</label><br>
      <input type="submit" value="Отправить запрос">
   </form>
</body>

</html>