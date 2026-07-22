<?php
include'config/connection.php';
include'config/Supervisor_API.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Action Page</title>
</head>
<body>

    <!-- Show Delete Confirmation if Deleted -->
    <?php if (!empty($delete_message)): ?>
        <p><?php echo $delete_message; ?></p>
    <?php endif; ?>

    <!-- Show Update Form if Edit was clicked -->
    <?php if ($view_data): ?>
        <form action="supervisor_update.php" method="post">
            <input type="hidden" name="newid" value="<?php echo $view_data['id']; ?>">
            
            <table border="1">
                <tr>
                    <td>route:</td>
                    <td><input type="text" name="route" value="<?php echo $view_data['route']; ?>"></td>
                </tr>
                <tr>
                    <td>peaces:</td>
                    <td><input type="text" name="peaces" value="<?php echo $view_data['peaces']; ?>"></td>
                </tr>
                <tr>
                    <td>stock:</td>
                    <td><input type="text" name="stock" value="<?php echo $view_data['stock']; ?>"></td>
                </tr>
            </table>
            <br>
            <input type="submit" name="submit" value="Update">&nbsp;
            <input type="reset" value="Reset">&nbsp;
            <input type="submit" name="can" value="Cancel">
        </form>
    <?php endif; ?>

</body>
</html>