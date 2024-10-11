<?php

/**
 * Plugin Name: Anissia
 * Description: Seamlessly Weave Your Digital Narrative
 * Version: 0.0.5
 */

// Add menu item to WordPress admin
function anissia_add_menu_item()
{
  add_menu_page(
    'Anissia',
    'Anissia',
    'manage_options',
    'anissia',
    'anissia_admin_page',
    'dashicons-networking',
    30
  );
}
add_action('admin_menu', 'anissia_add_menu_item');

// Admin page content
function anissia_admin_page()
{
?>
  <div class="wrap">
    <h1>Anissia - Digital Narrative Weaver</h1>
    <input type="text" id="keyword-input" placeholder="Enter keyword">
    <button id="generate-btn" class="button button-primary">Generate</button>
    <div id="result-table" style="margin-top: 20px;"></div> <!-- Add margin-top here -->
  </div>

  <script>
    jQuery(document).ready(function($) {
      $('#generate-btn').on('click', function() {
        var keyword = $('#keyword-input').val().trim();
        if (keyword !== '') {
          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'anissia_generate_table',
              keyword: keyword,
              depth: 0,
              map_path: JSON.stringify([keyword])
            },
            success: function(response) {
              $('#result-table').html(response);
              bindExpandButtons();
            }
          });
        }
      });

      function bindExpandButtons() {
        $('.expand-btn').on('click', function() {
          var $button = $(this); // Cache the button that was clicked
          var parentId = $button.data('id');
          var parent = $button.data('parent');
          var keyword = $button.data('keyword');
          var depth = $button.data('depth');
          var mapPath = $button.data('map-path');
          var concepts = $button.data('concepts');

          // Disable the button to prevent multiple clicks
          $button.prop('disabled', true);

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'anissia_expand_row',
              parent_id: parentId,
              parent: parent,
              keyword: keyword,
              depth: depth,
              map_path: JSON.stringify(mapPath),
              concepts: JSON.stringify(concepts)
            },
            success: function(response) {
              $('#result-table-body').append(response);
              bindExpandButtons(); // Rebind buttons for newly added rows
            }
          });
        });
      }

    });
  </script>
<?php
}

// AJAX handler for generating the initial table
function anissia_generate_table()
{
  $keyword = sanitize_text_field($_POST['keyword']);
  $depth = intval($_POST['depth']);
  $map_path = json_decode(stripslashes($_POST['map_path']), true);
  $children = anissia_get_children($keyword, array(), $keyword, $depth, $map_path);
  echo anissia_render_table($keyword, $children, 0, $depth, $map_path);
  wp_die();
}
add_action('wp_ajax_anissia_generate_table', 'anissia_generate_table');

// AJAX handler for expanding a row
function anissia_expand_row()
{
  $parent_id = intval($_POST['parent_id']);
  $parent = sanitize_text_field($_POST['parent']);
  $keyword = sanitize_text_field($_POST['keyword']); // The new parent for the expanded rows
  $depth = intval($_POST['depth']) + 1;
  $map_path = json_decode(stripslashes($_POST['map_path']), true);
  $concepts = json_decode(stripslashes($_POST['concepts']), true);

  // Get children based on the clicked keyword
  $children = anissia_get_children($keyword, $concepts, $map_path[0], $depth, $map_path);

  // Render the table, passing the new parent keyword
  echo anissia_render_table($keyword, $children, $parent_id, $depth, $map_path, $keyword);
  wp_die();
}
add_action('wp_ajax_anissia_expand_row', 'anissia_expand_row');

// Function to get children using the API
function anissia_get_children($keyword, $concepts, $map_name, $depth, $map_path)
{
  $url = "https://tools.cuik.io/app/cocon_semantique_v2";
  $data = array(
    'content' => $depth === 0 ? "A5" : "A7",
    'model' => "B4",
    'map_name' => $map_name,
    'concepts' => $concepts,
    'map_path' => $map_path,
    'timeout' => 15
  );

  $response = wp_remote_post($url, array(
    'body' => json_encode($data),
    'headers' => array('Content-Type' => 'application/json')
  ));

  if (is_wp_error($response)) {
    error_log('API error: ' . $response->get_error_message());
    return array(); // Return empty array if there's an error
  }

  $body = wp_remote_retrieve_body($response);
  error_log('API Response: ' . $body); // Log the response

  $decoded_data = json_decode($body, true);

  if (!$decoded_data) {
    error_log('Decoding error: ' . json_last_error_msg());
    return array(); // Return empty array if decoding fails
  }

  $children = json_decode(str_replace("'", '"', $decoded_data), true);

  if (!is_array($children)) {
    error_log('Children data not an array');
    return array(); // Return empty array if it's not a valid array
  }

  $result = array();
  foreach ($children as $child) {
    $result[uniqid()] = $child;
  }

  return $result;
}

// Function to render the table
function anissia_render_table($parent, $children, $parent_id = 0, $depth = 0, $map_path = array(), $current_keyword = '')
{
  $output = '';
  if ($depth == 0) {
    // Start the table only for the initial call
    $output .= '<table class="wp-list-table widefat fixed striped">';
    $output .= '<thead><tr><th>Parent</th><th>Children</th><th>Actions</th></tr></thead><tbody id="result-table-body">';
  }

  // Loop through each child and display in the table
  foreach ($children as $id => $child) {
    $new_map_path = array_merge($map_path, array($child));
    // Add a row for each child, setting the parent keyword correctly
    $output .= "<tr>
          <td>{$parent}</td>
          <td>{$child}</td>
          <td><button class='button expand-btn' data-id='{$id}' data-parent='{$parent}' data-keyword='{$child}' data-depth='{$depth}' data-map-path='" . esc_attr(json_encode($new_map_path, JSON_UNESCAPED_UNICODE)) . "' data-concepts='" . esc_attr(json_encode(array_values($children), JSON_UNESCAPED_UNICODE)) . "'>Expand</button></td>
      </tr>";
  }

  if ($depth == 0) {
    // Close the table only if it's the initial render
    $output .= '</tbody></table>';
  }

  return $output;
}
