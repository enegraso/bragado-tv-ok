package com.bragado.tvok

import androidx.media3.common.AudioAttributes
import androidx.media3.common.C
import androidx.media3.common.MediaItem
import androidx.media3.common.MediaMetadata
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.session.MediaLibraryService
import androidx.media3.session.MediaLibraryService.LibraryParams
import androidx.media3.session.LibraryResult
import androidx.media3.session.MediaLibraryService.MediaLibrarySession
import androidx.media3.session.MediaSession
import com.google.common.collect.ImmutableList
import com.google.common.util.concurrent.Futures
import com.google.common.util.concurrent.ListenableFuture

private const val ROOT_ID = "bragado-tv-root"
private const val LIVE_ID = "bragado-tv-en-vivo"
// Keep in sync with DEFAULT_STREAM_URL in App.js. A future iteration can fetch
// the remote config (config.txt) here, or have JS push the resolved URL.
private const val STREAM_URL = "https://srv1053170.hstgr.cloud/hls/bragadotv.m3u8"

/**
 * Android Auto media app service for Bragado TV.
 *
 * Plays the live HLS stream as AUDIO-ONLY so the app can be offered as a media
 * (radio) app in Android Auto. There is deliberately no video surface here:
 * Android Auto media apps are audio-only.
 */
class BragadoTvMediaService : MediaLibraryService() {

  private var mediaLibrarySession: MediaLibrarySession? = null

  override fun onCreate() {
    super.onCreate()

    val player = ExoPlayer.Builder(this).build()
    player.setAudioAttributes(
      AudioAttributes.Builder()
        .setUsage(C.USAGE_MEDIA)
        .setContentType(C.AUDIO_CONTENT_TYPE_MUSIC)
        .build(),
      /* handleAudioFocus= */ true
    )
    // Audio-only: never select the video track of the live HLS stream.
    player.trackSelectionParameters =
      player.trackSelectionParameters
        .buildUpon()
        .setTrackTypeDisabled(C.TRACK_TYPE_VIDEO, /* disabled= */ true)
        .build()
    player.playWhenReady = true

    mediaLibrarySession =
      MediaLibrarySession.Builder(this, player, BragadoTvCallback()).build()
  }

  override fun onGetSession(controllerInfo: MediaSession.ControllerInfo): MediaLibrarySession? =
    mediaLibrarySession

  override fun onDestroy() {
    mediaLibrarySession?.run {
      player.release()
      release()
      mediaLibrarySession = null
    }
    super.onDestroy()
  }

  private class BragadoTvCallback : MediaLibrarySession.Callback {

    override fun onGetLibraryRoot(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      params: LibraryParams?
    ): ListenableFuture<LibraryResult<MediaItem>> {
      val root =
        MediaItem.Builder()
          .setMediaId(ROOT_ID)
          .setMediaMetadata(
            MediaMetadata.Builder()
              .setTitle("Bragado TV")
              .setIsBrowsable(true)
              .setIsPlayable(false)
              .build()
          )
          .build()
      return Futures.immediateFuture(LibraryResult.ofItem(root, params))
    }

    override fun onGetChildren(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      parentId: String,
      page: Int,
      pageSize: Int,
      params: LibraryParams?
    ): ListenableFuture<LibraryResult<ImmutableList<MediaItem>>> {
      val children: ImmutableList<MediaItem> =
        if (parentId == ROOT_ID) ImmutableList.of(liveItem())
        else ImmutableList.of()
      return Futures.immediateFuture(LibraryResult.ofItemList(children, params))
    }

    private fun liveItem(): MediaItem =
      MediaItem.Builder()
        .setMediaId(LIVE_ID)
        .setUri(STREAM_URL)
        .setMediaMetadata(
          MediaMetadata.Builder()
            .setTitle("En vivo")
            .setArtist("Bragado TV")
            .setIsBrowsable(false)
            .setIsPlayable(true)
            .build()
        )
        .build()
  }
}
