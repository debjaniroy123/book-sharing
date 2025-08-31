<?php
/**
 * Plugin Name: Book Sharing
 * Description: A simple WordPress plugin for sharing books.
 * Version: 1.0
 * Author: 
 * */

// ================== ENQUEUE CSS & JS ==================
function book_sharing_enqueue_assets() {
    wp_enqueue_style('book-sharing-css', plugin_dir_url(_FILE_) . 'book-sharing.css');
    wp_enqueue_script('book-sharing-js', plugin_dir_url(_FILE_) . 'book-sharing.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'book_sharing_enqueue_assets');
add_action('admin_enqueue_scripts', 'book_sharing_enqueue_assets');

// ================== CREATE TABLES ON ACTIVATION ==================
function book_sharing_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Books table
    $table_name_books = $wpdb->prefix . 'book_sharing_books';
    $sql_books = "CREATE TABLE $table_name_books (
        id INT(11) NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        book_condition VARCHAR(255) NOT NULL,
        availability ENUM('available', 'borrowed') DEFAULT 'available',
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Requests table
    $table_name_requests = $wpdb->prefix . 'book_sharing_requests';
    $sql_requests = "CREATE TABLE $table_name_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        book_id INT(11) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        message TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Reviews table
    $table_name_reviews = $wpdb->prefix . 'book_sharing_reviews';
    $sql_reviews = "CREATE TABLE $table_name_reviews (
        id INT(11) NOT NULL AUTO_INCREMENT,
        book_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        rating INT(1) NOT NULL,
        review TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_books);
    dbDelta($sql_requests);
    dbDelta($sql_reviews);
}
register_activation_hook(_FILE_, 'book_sharing_activate');

// ================== CLEAN TABLES ON UNINSTALL ==================
function book_sharing_uninstall() {
    global $wpdb;
    $table_name_books = $wpdb->prefix . 'book_sharing_books';
    $table_name_requests = $wpdb->prefix . 'book_sharing_requests';
    $table_name_reviews = $wpdb->prefix . 'book_sharing_reviews';
    $wpdb->query("DROP TABLE IF EXISTS $table_name_books, $table_name_requests, $table_name_reviews");
}
register_uninstall_hook(_FILE_, 'book_sharing_uninstall');

// ================== ADMIN MENU ==================
function book_sharing_menu() {
    add_menu_page(
        'Book Sharing',
        'Book Sharing',
        'manage_options',
        'book_sharing',
        'book_sharing_admin_page'
    );
}
add_action('admin_menu', 'book_sharing_menu');

// ================== ADMIN PAGE ==================
function book_sharing_admin_page() {
    if (isset($_POST['add_book'])) {
        book_sharing_add_book($_POST['title'], $_POST['author'], $_POST['category'], $_POST['condition']);
    }

    if (isset($_POST['delete_book'])) {
        book_sharing_delete_book($_POST['book_id']);
    }

    ?>
    <h1>Book Sharing Admin</h1>
    <form method="post" class="book-sharing-form">
        <label for="title">Title:</label>
        <input type="text" name="title" required>
        <br><br>
        <label for="author">Author:</label>
        <input type="text" name="author" required>
        <br><br>
        <label for="category">Category:</label>
        <input type="text" name="category" required>
        <br><br>
        <label for="condition">Condition:</label>
        <select name="condition" required>
            <option value="new">New</option>
            <option value="used">Used</option>
        </select>
        <br><br>
        <input type="submit" name="add_book" value="Add Book">
    </form>

    <h2>Manage Books</h2>
    <?php
    global $wpdb;
    $table_name_books = $wpdb->prefix . 'book_sharing_books';
    $books = $wpdb->get_results("SELECT * FROM $table_name_books");

    if ($books) {
        echo "<table class='book-sharing-table'><tr><th>Title</th><th>Author</th><th>Category</th><th>Condition</th><th>Actions</th></tr>";
        foreach ($books as $book) {
            echo "<tr>
                <td>{$book->title}</td>
                <td>{$book->author}</td>
                <td>{$book->category}</td>
                <td>{$book->book_condition}</td>
                <td>
                    <form method='post' class='book-delete-form'>
                        <input type='hidden' name='book_id' value='{$book->id}'>
                        <input type='submit' name='delete_book' value='Delete'>
                    </form>
                </td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No books found</p>";
    }
}

// ================== HELPER FUNCTIONS ==================
function book_sharing_add_book($title, $author, $category, $condition) {
    global $wpdb;
    $table_name_books = $wpdb->prefix . 'book_sharing_books';
    $wpdb->insert($table_name_books, [
        'title' => sanitize_text_field($title),
        'author' => sanitize_text_field($author),
        'category' => sanitize_text_field($category),
        'book_condition' => sanitize_text_field($condition)
    ]);
}

function book_sharing_delete_book($book_id) {
    global $wpdb;
    $table_name_books = $wpdb->prefix . 'book_sharing_books';
    $wpdb->delete($table_name_books, ['id' => $book_id]);
}

// ================== FRONTEND SHORTCODE ==================
// Search bar and review/rating system added
function book_sharing_frontend_shortcode() {
    global $wpdb;
    $table_name_books = $wpdb->prefix . 'book_sharing_books';

    // Handle search/filter
    $where = "WHERE availability = 'available'";
    $search_title = isset($_GET['search_title']) ? sanitize_text_field($_GET['search_title']) : '';
    $search_author = isset($_GET['search_author']) ? sanitize_text_field($_GET['search_author']) : '';
    $search_category = isset($_GET['search_category']) ? sanitize_text_field($_GET['search_category']) : '';

    if ($search_title) {
        $where .= " AND title LIKE '%$search_title%'";
    }
    if ($search_author) {
        $where .= " AND author LIKE '%$search_author%'";
    }
    if ($search_category) {
        $where .= " AND category LIKE '%$search_category%'";
    }

    $books = $wpdb->get_results("SELECT * FROM $table_name_books $where");

    // Handle borrow request
    $confirmation = '';
    if (isset($_POST['borrow_book']) && isset($_POST['book_id'])) {
        if (is_user_logged_in()) {
            book_sharing_handle_borrow_request($_POST['book_id']);
            $confirmation = "<p class='book-sharing-confirm book-sharing-success'>Your borrow request has been sent!</p>";
        } else {
            $confirmation = "<p class='book-sharing-confirm book-sharing-error'>You must be logged in to borrow a book.</p>";
        }
    }

    // Handle review submission
    if (isset($_POST['submit_review']) && isset($_POST['book_id'])) {
        if (is_user_logged_in()) {
            book_sharing_handle_review($_POST['book_id'], $_POST['rating'], $_POST['review']);
            $confirmation .= "<p class='book-sharing-confirm book-sharing-success'>Thank you for your review!</p>";
        } else {
            $confirmation .= "<p class='book-sharing-confirm book-sharing-error'>You must be logged in to review a book.</p>";
        }
    }

    // Search/filter form
    $output = $confirmation;
    $output .= '<form method="get" class="book-sharing-search-form"><input type="hidden" name="page_id" value="' . get_the_ID() . '">';
    $output .= 'Title: <input type="text" name="search_title" value="' . esc_attr($search_title) . '"> ';
    $output .= 'Author: <input type="text" name="search_author" value="' . esc_attr($search_author) . '"> ';
    $output .= 'Category: <input type="text" name="search_category" value="' . esc_attr($search_category) . '"> ';
    $output .= '<input type="submit" value="Search"></form><br>';

    if ($books) {
        $output .= "<h2>Available Books</h2><ul class='book-sharing-list'>";
        foreach ($books as $book) {
            $output .= "<li class='book-sharing-item'><strong>{$book->title}</strong> by {$book->author} - {$book->category} (Condition: {$book->book_condition})";

            // Borrow form
            $output .= "<form method='post' class='book-sharing-borrow-form' style='display:inline-block; margin-left:10px;'>
                <input type='hidden' name='book_id' value='{$book->id}'>
                <input type='submit' name='borrow_book' value='Borrow'>
            </form>";

            // Review form
            $output .= "<form method='post' class='book-sharing-review-form' style='display:inline-block; margin-left:10px;'>
                <input type='hidden' name='book_id' value='{$book->id}'>
                Rating: <select name='rating'>
                    <option value='1'>1</option>
                    <option value='2'>2</option>
                    <option value='3'>3</option>
                    <option value='4'>4</option>
                    <option value='5'>5</option>
                </select>
                <input type='text' name='review' placeholder='Write a review' required>
                <input type='submit' name='submit_review' value='Submit Review'>
            </form>";

            // Show reviews
            $output .= book_sharing_show_reviews($book->id);

            $output .= "</li><br>";
        }
        $output .= "</ul>";
    } else {
        $output .= "<p>No books available</p>";
    }
    return $output;
}
add_shortcode('book_list', 'book_sharing_frontend_shortcode');

// ================== HANDLE BORROW REQUESTS ==================
function book_sharing_handle_borrow_request($book_id) {
    global $wpdb;
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    $table_name_requests = $wpdb->prefix . 'book_sharing_requests';
    $wpdb->insert($table_name_requests, [
        'user_id' => $user_id,
        'book_id' => intval($book_id),
        'status' => 'pending'
    ]);
}

// ================== REVIEW & RATING ==================
function book_sharing_handle_review($book_id, $rating, $review) {
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name_reviews = $wpdb->prefix . 'book_sharing_reviews';
    $wpdb->insert($table_name_reviews, [
        'book_id' => intval($book_id),
        'user_id' => $user_id,
        'rating' => intval($rating),
        'review' => sanitize_text_field($review)
    ]);
}

function book_sharing_show_reviews($book_id) {
    global $wpdb;
    $table_name_reviews = $wpdb->prefix . 'book_sharing_reviews';
    $reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name_reviews WHERE book_id = %d", $book_id));
    $output = "<div class='book-sharing-review'><strong>Reviews:</strong><ul>";
    if ($reviews) {
        foreach ($reviews as $r) {
            $output .= "<li>Rating: {$r->rating}/5 - " . esc_html($r->review) . "</li>";
        }
    } else {
        $output .= "<li>No reviews yet.</li>";
    }
    $output .= "</ul></div>";
    return $output;
}
