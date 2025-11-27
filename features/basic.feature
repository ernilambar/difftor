Feature: Test folders

  Background:
    Given a WP install
    And a foldiff-test/folder1/file1.txt file:
      """
      Original content
      Line 2
      """

    And a foldiff-test/folder1/file2.txt file:
      """
      This file exists only in folder1
      """

    And a foldiff-test/folder2/file1.txt file:
      """
      Modified content
      Line 2 changed
      """

    And a foldiff-test/folder2/file3.txt file:
      """
      This file exists only in folder2
      """

  Scenario: Compare two local folders

    When I run `wp difftor foldiff-test/folder1 foldiff-test/folder2 --porcelain`
    Then STDOUT should not be empty
    And save STDOUT as {HTML_DIFF_FILE}
    And the {HTML_DIFF_FILE} file should exist
    And the {HTML_DIFF_FILE} file should contain:
      """
      Removed Files (1)
      """
    And the {HTML_DIFF_FILE} file should contain:
      """
      Added Files (1)
      """
    And the {HTML_DIFF_FILE} file should contain:
      """
      file1.txt</a>
      """
    And the {HTML_DIFF_FILE} file should contain:
      """
      <ins>Modified</ins>
      """
