<?php
session_start();

include "db.php";

/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, fullname, password FROM users WHERE email = ?");

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];

            header("Location: ../dashboard/home.php");
            exit();

        } else {

            header("Location: ../login.php?error=Incorrect Password");
            exit();

        }

    } else {

        header("Location: ../login.php?error=Email Not Found");
        exit();

    }

} else {

    header("Location: ../login.php");
    exit();

}
?>